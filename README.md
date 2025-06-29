## Installation
brew install mkcert
mkcert -install
mkcert localhost   
docker compose build composer
docker compose run --rm composer install 
docker compose run --rm npm install

## Running the application locally
docker compose up app --build
cd src && php artisan migrate

## Shell into the PHP container

### Find ID of PHP container
docker ps

### Execute the shell command
docker exec -it {PHP_CONTAINER_ID} sh 





