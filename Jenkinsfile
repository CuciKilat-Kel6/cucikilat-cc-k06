pipeline {
  agent any

  options {
    skipDefaultCheckout(true)
    buildDiscarder(logRotator(numToKeepStr: '10'))
    timeout(time: 30, unit: 'MINUTES')
  }

  environment {
    IMAGE_REPO = 'thonifasya/cucikilat-app'
    TEST_IMAGE = "cucikilat-test:${BUILD_NUMBER}"
    TEST_NET   = "tubes-test-${BUILD_NUMBER}"
    TEST_DB    = "tubes-db-${BUILD_NUMBER}"
  }

  stages {
    stage('Checkout') {
      steps {
        checkout scm
        bat 'git log -1 --oneline'
      }
    }

    stage('Lint') {
      steps {
        bat 'docker build --target test -t %TEST_IMAGE% -f dockerfile .'
        bat 'docker run --rm %TEST_IMAGE% composer lint'
      }
    }

    stage('Test') {
      steps {
        bat 'docker network create %TEST_NET%'
        bat 'docker run -d --name %TEST_DB% --network %TEST_NET% -e MYSQL_ROOT_PASSWORD=rootpass -e MYSQL_DATABASE=cucikilat_test mysql:8.0'
        timeout(time: 3, unit: 'MINUTES') {
          waitUntil {
            script {
              def rc = bat(returnStatus: true, script: '@docker exec %TEST_DB% mysqladmin ping -h 127.0.0.1 -uroot -prootpass --silent')
              return rc == 0
            }
          }
        }
        bat 'docker run --rm --network %TEST_NET% -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= -e DB_HOST=%TEST_DB% -e DB_PORT=3306 -e DB_USERNAME=root -e DB_PASSWORD=rootpass -e DB_DATABASE=cucikilat_test %TEST_IMAGE% sh -c "cp .env.testing.example .env && php artisan test"'
      }
    }

    stage('Build Image') {
      steps {
        bat 'docker build --target production -t %IMAGE_REPO%:%BUILD_NUMBER% -t %IMAGE_REPO%:latest -f dockerfile .'
      }
    }

    stage('Push Docker Hub') {
      steps {
        withCredentials([usernamePassword(credentialsId: 'dockerhub-cucikilat', usernameVariable: 'DH_USER', passwordVariable: 'DH_TOKEN')]) {
          bat 'echo %DH_TOKEN%| docker login -u %DH_USER% --password-stdin'
          bat 'docker push %IMAGE_REPO%:%BUILD_NUMBER%'
          bat 'docker push %IMAGE_REPO%:latest'
        }
      }
    }
  }

  post {
    always {
      bat(returnStatus: true, script: '@docker rm -f %TEST_DB%')
      bat(returnStatus: true, script: '@docker network rm %TEST_NET%')
      bat(returnStatus: true, script: '@docker rmi %TEST_IMAGE%')
      bat(returnStatus: true, script: '@docker logout')
    }
    success { echo 'Pipeline sukses' }
    failure { echo 'Pipeline gagal, cek stage yang merah' }
  }
}
