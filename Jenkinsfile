pipeline {
  agent any

  options {
    skipDefaultCheckout(true)
    buildDiscarder(logRotator(numToKeepStr: '10'))
  }

  stages {
    stage('Checkout') {
      steps {
        checkout scm
        bat 'git log -1 --oneline'
      }
    }

    stage('Cek Lingkungan') {
      steps {
        bat 'docker version'
        bat 'docker compose version'
      }
    }
  }

  post {
    success { echo 'Pipeline sukses' }
    failure { echo 'Pipeline gagal, cek stage yang merah' }
    always  { echo 'Selesai' }
  }
}
