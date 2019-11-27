# Configuration

Lighthouse comes with sensible configuration defaults and works right out of the box.
Should you feel the need to change your configuration, you need to publish the configuration file first.

```bash
php artisan vendor:publish --provider="Nuwave\Lighthouse\LighthouseServiceProvider" --tag=config
```

The configuration file will be placed in `config/lighthouse.php`.
