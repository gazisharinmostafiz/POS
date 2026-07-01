# Tablet and Mobile Testing Checklist

Test on at least one Android tablet, one Android phone, and one browser PWA install.

1. Install the PWA from Chrome and confirm it opens in standalone mode.
2. Install the debug APK and confirm it reaches the configured backend URL.
3. Log in as platform owner and tenant admin.
4. Log in as waiter, kitchen, and counter users.
5. Confirm waiter table selection, menu search, category tabs, and cart controls are touch-friendly.
6. Confirm kitchen columns remain readable in landscape and portrait.
7. Confirm counter billing panels can scroll without overlapping controls.
8. Confirm chat opens and messages remain usable on a phone viewport.
9. Toggle offline mode and verify the PWA offline fallback appears for navigation.
10. Confirm `/health` is reachable from the device network.
11. Confirm no card numbers, CVV, PIN, or sensitive provider secrets appear in device logs.
12. Confirm printer failures do not block order creation; printer bridge testing is deferred.
