IntaroCRM - LeadsPartner integration tool
===================

Tool for simple integration between [IntaroCRM](http://www.intarocrm.ru/) and [LeadsPartner](http://leadspartner.ru/).

## Getting started / Installation

* Download this project via any [git](http://git-scm.com/) client (checkout master branch), or as a [zip archive](https://github.com/intarocrm/leadspartner-client/archive/master.zip).
* Install [composer](https://getcomposer.org/) into the project directory.

### Composer install guide & tool installation via composer
* Download [composer](https://getcomposer.org/download/)
* Use command `php composer.phar update` to download vendors.
 
### Settings .ini file
It is needed to setup your configutarions in a `config/parameters.ini` file (example is a `config/parameters-dist.ini` file).

### Sample of code for enter page for transaction fixing

```php
require_once __DIR__ . '/leadspartner-client/vendor/autoload.php'; // require autoloader
$intaroApi = new LeadsPartner\Helpers\ApiHelper(); // create api helper
$intaroApi->setAdditionalParameters($_SERVER['QUERY_STRING']); // setting additional params in user cookies
```

### Sample of code for form processing script

```php
require_once __DIR__ . '/leadspartner-client/vendor/autoload.php'; // require autoloader
$intaroApi = new LeadsPartner\Helpers\ApiHelper(); // create api helper

$order = array(
    'orderMethod'  => 'some-order-method',
    'customer' => array(
        'fio'   => 'user name',
        'phone' => array('+79123456789'),
                
    ),
    'customFields' => array(
        'form_type' => 'some-form-type'
    ),
    'items' => array(
        array(
            'quantity' => 1,
            'productId' => 1,
        ),
    ),
);

$intaroApi->orderCreate($order);
```

### Cron setup

Add this command, to send request with changed statuses to LeadsPartner every 5 mins:

```bash
*/5 * * * * php leadspartner-client/console.php update-cpa
```

