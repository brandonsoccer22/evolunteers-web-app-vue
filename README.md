## Installation
`brew install mkcert`
`mkcert -install`
`mkcert localhost` #Add certs to /certs in the project root
`docker compose build composer`
`docker compose run --rm composer install`
`docker compose run --rm npm install`

## Running the application locally
docker compose up app --build
`cd src && php artisan migrate`

## Shell into the PHP container
task sh

### Find ID of PHP container
docker ps

### Execute the shell command
`docker exec -it {PHP_CONTAINER_ID} sh`

### Run PHP Units tests
task phpunit

## Test User
Run `php artisan user:create-test`

## Bruno
Run `mkcert -CAROOT` and add rootCA.pem as a Custom CA Certificate in Bruno
Set the environment to local and update the email and password 






