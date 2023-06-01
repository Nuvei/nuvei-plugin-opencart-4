# Nuvei Checkout Plugin for OpenCart 4

---

# 1.3
```
    * Changed sourceApplication and webMasterId parameters values.
    * Fix for the case when on the checkout page, the client choose Nuvei, then another payent provider, and Nuvei payment options stay visible.
    * Removed NUVEI_PLUGIN_V constant. Now will get the plugin version from the install.json.
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