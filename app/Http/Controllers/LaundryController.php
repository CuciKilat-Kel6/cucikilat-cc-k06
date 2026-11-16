<?php

namespace App\Http\Controllers;

use App\Models\LaundryOrder;
use App\Models\Service;
use App\Services\OrderPricing;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LaundryController extends Controller
{
    public function dashboard(Request $request): View
    {
        $request->validate(['q' => 'nullable|string|max:100', 'status' => ['nullable', Rule::in(LaundryOrder::STATUSES)]]);
        $orders = LaundryOrder::query()->when($request->filled('q'), fn ($q) => $q->where(function ($q) use ($request) {
            $q->where('code', 'like', '%'.$request->q.'%')->orWhere('customer_name', 'like', '%'.$request->q.'%');
        }))->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))->latest()->paginate(12)->withQueryString();

        return view('dashboard', ['orders' => $orders, 'active' => LaundryOrder::where('status', '!=', 'DIAMBIL')->count(), 'ready' => LaundryOrder::where('status', 'SIAP_DIAMBIL')->count(), 'revenue' => LaundryOrder::whereDate('paid_at', today())->sum('total')]);
    }

    public function form(?LaundryOrder $order = null): View
    {
        return view('order-form', ['order' => $order, 'services' => Service::orderBy('name')->get()]);
    }

    private function orderData(Request $request, Service $service): array
    {
        return $request->validate(['customer_name' => 'required|string|max:100', 'phone' => 'required|regex:/^[+0-9 ()-]{8,25}$/', 'notes' => 'nullable|string|max:1000', 'weight' => $service->type === 'satuan' ? 'exclude' : 'required|numeric|decimal:0,3|gt:0|max:1000', 'quantity' => $service->type === 'satuan' ? 'required|integer|min:1|max:1000' : 'exclude']);
    }

    private function pricedData(Request $request, Service $service, OrderPricing $pricing, CarbonInterface $created): array
    {
        $data = $this->orderData($request, $service);

        return array_merge(collect($data)->only(['customer_name', 'phone', 'notes'])->all(), $pricing->calculate($service, $data['weight'] ?? null, isset($data['quantity']) ? (int) $data['quantity'] : null), ['service_id' => $service->id, 'service_name' => $service->name, 'service_type' => $service->type, 'unit_price' => $service->price, 'due_at' => $created->copy()->addDays($service->type === 'express' ? 1 : 3)]);
    }

    public function store(Request $request, OrderPricing $pricing): RedirectResponse
    {
        $request->validate(['submission_key' => 'required|uuid']);
        try {
            $order = DB::transaction(function () use ($request, $pricing) {
                $existing = LaundryOrder::where('submission_key', $request->submission_key)->first();
                if ($existing) {
                    return $existing;
                }
                $request->validate(['service_id' => 'required|integer|exists:services,id,deleted_at,NULL']);
                $service = Service::lockForUpdate()->findOrFail($request->service_id);

                return LaundryOrder::create(array_merge($this->pricedData($request, $service, $pricing, now()), [
                    'submission_key' => $request->submission_key,
                    'code' => 'CK-'.strtoupper(Str::random(12)),
                    'status' => 'DITERIMA',
                    'history' => [['status' => 'DITERIMA', 'at' => now()->toIso8601String()]],
                ]));
            });
        } catch (UniqueConstraintViolationException $exception) {
            $order = LaundryOrder::where('submission_key', $request->submission_key)->first();
            if (! $order) {
                throw $exception;
            }
        }

        return redirect()->route('orders.show', $order)->with('success', 'Order masuk! Kode lacak: '.$order->code);
    }

    public function show(LaundryOrder $order): View
    {
        return view('order-show', compact('order'));
    }

    public function update(Request $request, LaundryOrder $order, OrderPricing $pricing): RedirectResponse
    {
        DB::transaction(function () use ($request, $order, $pricing) {
            $locked = LaundryOrder::lockForUpdate()->findOrFail($order->id);
            abort_if($locked->status === 'DIAMBIL', 409, 'Order yang sudah diambil tidak dapat diubah.');
            abort_if($locked->paid_at !== null, 409, 'Order sudah dibayar. Harga dan detail order dikunci.');
            $request->validate(['service_id' => 'required|integer|exists:services,id,deleted_at,NULL']);
            $service = Service::lockForUpdate()->findOrFail($request->service_id);
            $locked->update($this->pricedData($request, $service, $pricing, $locked->created_at));
        });

        return redirect()->route('orders.show', $order)->with('success', 'Detail order diperbarui.');
    }

    public function status(Request $request, LaundryOrder $order): RedirectResponse
    {
        $request->validate(['status' => ['required', Rule::in(LaundryOrder::STATUSES)]]);
        DB::transaction(function () use ($request, $order) {
            $locked = LaundryOrder::lockForUpdate()->findOrFail($order->id);
            abort_if($locked->status === 'DIAMBIL', 409, 'Order yang sudah diambil tidak dapat diubah.');
            $next = LaundryOrder::STATUSES[array_search($locked->status, LaundryOrder::STATUSES) + 1];
            abort_unless($request->status === $next, 422, 'Status harus maju satu tahap.');
            abort_if($next === 'DIAMBIL' && ! $locked->paid_at, 422, 'Catat pembayaran sebelum order diambil.');
            $history = $locked->history;
            $history[] = ['status' => $next, 'at' => now()->toIso8601String()];
            $locked->update(['status' => $next, 'history' => $history]);
        });

        return back()->with('success', 'Status order diperbarui.');
    }

    public function pay(LaundryOrder $order): RedirectResponse
    {
        DB::transaction(function () use ($order) {
            $locked = LaundryOrder::lockForUpdate()->findOrFail($order->id);
            abort_if($locked->status === 'DIAMBIL', 409, 'Order yang sudah diambil tidak dapat diubah.');
            if (! $locked->paid_at) {
                $locked->update(['paid_at' => now()]);
            }
        });

        return back()->with('success', 'Pembayaran dicatat.');
    }

    public function track(Request $request): View
    {
        $data = $request->validate(['code' => 'nullable|string|max:30']);
        $code = strtoupper(trim($data['code'] ?? ''));
        $order = $code !== '' ? LaundryOrder::where('code', $code)->first() : null;

        return view('track', compact('order', 'code'));
    }

    public function services(): View
    {
        return view('services', ['services' => Service::orderBy('id')->get()]);
    }

    private function serviceData(Request $request, ?Service $service = null): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('services')->ignore($service?->id)], 'type' => ['required', Rule::in(['reguler', 'express', 'satuan'])], 'price' => 'required|integer|min:1|max:10000000', 'description' => 'nullable|string|max:255']);
    }

    public function saveService(Request $request, ?Service $service = null): RedirectResponse
    {
        $data = $this->serviceData($request, $service);
        if ($service) {
            $service->update($data);
        } else {
            Service::create($data);
        }

        return back()->with('success', 'Layanan disimpan.');
    }

    public function deleteService(Service $service): RedirectResponse
    {
        $service->delete();

        return back()->with('success', 'Layanan diarsipkan. Order lama tetap tersimpan.');
    }

    public function report(Request $request): View
    {
        $data = $request->validate(['date' => 'nullable|date_format:Y-m-d']);
        $date = $data['date'] ?? today()->toDateString();
        $paid = LaundryOrder::whereDate('paid_at', $date)->orderBy('paid_at')->get();

        return view('report', compact('paid', 'date'));
    }
}
