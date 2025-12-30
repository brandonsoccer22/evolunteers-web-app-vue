# eVol

eVol is a multi-tenant application that manages users, organizations, and opportunities.
Users belong to one or more organizations, and organizations own opportunities.
Admin users manage the global user base, while organization users manage their own organizations and their associated opportunities.
Regular users can search for all opportunities across organization (with read only access).

## Installation

Ensure Docker is installed.

Ensure task is installed: `brew install go-task/tap/go-task`

(duplicate copy commands are intentional)

`brew install mkcert`
`mkcert -install`
`cd certs && mkcert localhost && cd ..`
`cp src/.env.example src/.env`
`cp src/.env src/.env.docker`
`cp src/.env src/.env.testing`
`task build`
`task composer -- install`
`php src/artisan key:generate`
`cp src/.env src/.env.docker`
`cp src/.env src/.env.testing`
`task artisan -- migrate`
`task artisan -- user:create-test --email=test_user@example.com --password=your_password`
`task artisan -- db:seed-with-options --organizations=5 --opportunities=10`
`cd src && npm install`
`npm run build-dev`

## Running the application locally

`task up`

## Stopping application

`task down`

## Other useful commands

### Shell into the PHP container

`task sh`

### Run Pest tests

task test

## Test User

Run `php artisan user:create-test`

## Bruno

Run `mkcert -CAROOT` and add rootCA.pem as a Custom CA Certificate in Bruno
Set the environment to local and update the email and password

## Roadmap

- Opportunity image/file management (upload, storage, and display)
- User sign up for Opportunities (registration/volunteering workflow)
- .ics export for Opportunities (calendar integration)





