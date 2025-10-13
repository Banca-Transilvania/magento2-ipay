## BT iPay

This is an online payment module for Magento 2, that requires an account with BT bank.

### Magento versions compatibility :

**Which version should I use?**

| Magento Version                                   | Module Version                                               |
|---------------------------------------------------|--------------------------------------------------------------|
| Magento **2.4.x** Opensource (CE) / Commerce (EE) | **100.x.x** latest release: ```composer require btrl/ipay``` |

### How to use

1. Install the module via Composer :

``` composer require btrl/ipay ```

2. Enable it

``` bin/magento module:enable BTRL_Ipay ```

3. Install the module and rebuild the DI cache

``` bin/magento setup:upgrade ```


### How to configure

> Stores > Configuration > Sales > Payment Methods > BT iPay Payment
