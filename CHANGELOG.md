# Nuvei Checkout Plugin for OpenCart 4

---

# 1.9
```
    * Added a specific parameter to the Simple Connect when the plugin is used on QA site.
    * Removed the plugin option to auto-close or not the APM popup.
    * When the plugin cannot find an Order for some Sale/Auth DMN it will save a notification in the admin.
    * Added option to turn off/on the auto-void.
    * The Subscription functionality was enabled again.
```

# 1.8
```
    * Add option to mask/unmask user details in the log.
    * Fixed a JS check on the checkout page.
    * Destroy the checkout object before calling it.
    * Show the plugin version in Help Tools.
    * Fix for the sourceApplication parameter.
    * Pass sourceApplication to Simply Connect.
    * Fix for the problem on the Checkout when the client try to get available payment methods. This problem was result of changed method name in OC4 Cart object.
    * The plugin was disabled for products with Subscription. All events which serve subscriprion logic are also disabled. The functionality will be enable when OC4 provaide stable Subscriptions logic.
    * Fix for the "Save Logs" menu.
```

# 1.7-p1
```
    * Added Tag SDK URL for test cases.
```

# 1.7
```
    * Fix for the "SDK translations" setting example.
    * Added locale for Gpay button.
```

# 1.6
```
    * Do not call updateOrder on SDK pre-payment method.
    * Enable Nuvei GW for Zero-Total Orders, but allow only Credit Cards.
    * Disable DCC when Order total is Zero.
    * Added the amount and the currency into Order's notes.
    * Save Order note for Pending DMN.
    * Fix for the pre-payment check, in case when in session missing the Order ID.
```

# 1.5
```
    * Fixed the URL to the current plugin repo, when check for new version.
    * Changed Sandbox REST API URL.
    * Added option to change SimplyConnect theme.
    * Allways pass userTokenId in OpenOrder rquest.
    * Return code 400 to the Cashier, when the plugin did not find an OC Order nby the DMN data.
    * Added Auto-Void logic.
    * Trim merchant credentials after get them.
```

# 1.4
```
    * Support OC 4.0.2.1.
    * Removed the option to change SDK version.
```

# 1.3
```
    * Changed sourceApplication and webMasterId parameters values.
    * Fix for the case when on the checkout page, the client choose Nuvei, then another payent provider, and Nuvei payment options stay visible.
    * Removed NUVEI_PLUGIN_V constant. Now will get the plugin version from the install.json.
    * Fix for Nuvei_Class calls to create_log method. Added the missing plugin settings parameter.
```

# 1.2
```
    * Force transaction type to Auth when Order total amount is 0.
```

# 1.1
```
    * Fixed the case when updateOrder can not update userTokenId parameter.
    * Do not pass billingAddress and userData objects to the SDK call anymore.
```

# 1.0
```
    * First version.
```
