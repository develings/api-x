# API X (Work in progress)

![Logo](logo.png)

<p align="center">
<a href="https://github.com/develings/api-x/actions"><img src="https://github.com/develings/api-x/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/develings/api-x"><img src="https://img.shields.io/packagist/dt/develings/api-x" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/develings/api-x"><img src="https://img.shields.io/packagist/v/develings/api-x" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/develings/api-x"><img src="https://img.shields.io/packagist/l/develings/api-x" alt="License"></a>
</p>

### BE CAREFUL
This package is still under heavy development and may contain breaking changes with every update.


### About
Create a full-fledged API only using a simple JSON file.

### TODO
- [ ] Pagination
- [ ] Search
- [ ] 

#### Search
Search should be easy

```json
{ "name": "string|search:like" },
{ "description": "string|search:like_left" },
{ "description": "string|search:like_right" },
{ "uuid": "string|search:equal" },
```

#### Simple example
```json
{
    "name": "App",
    "version": "1.0",
    "description": "A demo application using the API library",
    "endpoint": "/api/v1.0/",
    "authentication": "token:users,api_key",
    "events": true,
    "db": {
        "driver": "mariadb",
        "prefix": "app_test_"
    },
    "servers": [
        {
            "url": "http://app.test"
        },
        {
            "url": "https://someId.execute-api.eu-central-1.amazonaws.com/dev"
        }
    ],
    "api": [
        {
            "name": "device",
            "timestamps": true,
            "soft_deletes": true,
            "identifier": "uuid",
            "sort_key": "created_at",
            "per_page": 10,
            "fields": {
                "uuid": "string|primary|default:uuid",
                "device_id": "string:64|unique",
                "last_active_at": "datetime|index|on_update_fill:datetime",
                "device_user_id": "uuid|nullable",
                "api_key": "string|default:alphanumeric,36"
            },
            "relations": {
                "user": "belongsTo:users"
            }
        }
    ]
}
```

We currently support the normal laravel DB drivers.

This definition will create an OpenAPI specification route plus a
migration for the device table including the endpoints for it.

All that needs to be done is to instantiate the API class.

## Install

You just need to require the composer package, and you're done.

```shell script
composer install develings/api-x
```

Create api.json in the root of your project.
```shell script
php artisan api:make
```

## Instantiate
```php
// add this to config/app.php
'providers' => [
    ...
    \API\APIServiceProvider::class,
],

// add this to AppServiceProvider.php in boot()
Model::unguard();

// add the route (e.g. routes/web.php)
$api = new API\API(base_path('api.json'));
$api->setRoutes();
``` 

## Fake data

Since we have all the definition we need from the **api.json** file, it's also possible
to populate test data using the **faker** package. **Coming soon**

## Planned features

Use route syntax to fetch code from PHP instead of api.json. Eg:

```json
{
    "api": [
        "@App\\API\\DeviceAPI",
        "@App\\API\\UserAPI:getDefinition",
        {
            "name": "company",
            "fields": "@App\\API\\CompanyAPI:getFields"
        }
    ]
}
```

 
