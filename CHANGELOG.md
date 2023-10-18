# Nuvei Checkout Plugin for OpenCart 4

---

# 1.6
```
    * Do not call updateOrder on SDK pre-payment method.
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
