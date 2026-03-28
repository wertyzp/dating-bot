# hello-bot


## Setup
- `composer install`
- `cp .env.example .env`
- Fill in the `.env` file with your bot token and other info
- `php artisan telegram:set-my-commands`
- `php artisan telegram:set-webhook --url=https://your-domain.com/hook`
