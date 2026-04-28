# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: logs.spec.js >> Logs >> fetching logs loads content
- Location: tests/e2e/logs.spec.js:29:3

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: locator('#ash-nazg-log-content, .ash-nazg-logs-content')
Expected: visible
Timeout: 15000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" with timeout 15000ms
  - waiting for locator('#ash-nazg-log-content, .ash-nazg-logs-content')

```

# Page snapshot

```yaml
- generic [ref=e2]:
  - navigation "Main menu":
    - link "Skip to main content" [ref=e3] [cursor=pointer]:
      - /url: "#wpbody-content"
    - link "Skip to toolbar" [ref=e4] [cursor=pointer]:
      - /url: "#wp-toolbar"
    - list [ref=e7]:
      - listitem [ref=e8]:
        - link "Dashboard" [ref=e9] [cursor=pointer]:
          - /url: index.php
          - generic [ref=e10]: 
          - generic [ref=e11]: Dashboard
        - list [ref=e12]:
          - listitem [ref=e13]:
            - link "Home" [ref=e14] [cursor=pointer]:
              - /url: index.php
          - listitem [ref=e15]:
            - link "Updates 1" [ref=e16] [cursor=pointer]:
              - /url: update-core.php
              - text: Updates
              - generic [ref=e17]: "1"
      - listitem [ref=e18]
      - listitem [ref=e20]:
        - link "Posts" [ref=e21] [cursor=pointer]:
          - /url: edit.php
          - generic [ref=e22]: 
          - generic [ref=e23]: Posts
        - list [ref=e24]:
          - listitem [ref=e25]:
            - link "All Posts" [ref=e26] [cursor=pointer]:
              - /url: edit.php
          - listitem [ref=e27]:
            - link "Add Post" [ref=e28] [cursor=pointer]:
              - /url: post-new.php
          - listitem [ref=e29]:
            - link "Categories" [ref=e30] [cursor=pointer]:
              - /url: edit-tags.php?taxonomy=category
          - listitem [ref=e31]:
            - link "Tags" [ref=e32] [cursor=pointer]:
              - /url: edit-tags.php?taxonomy=post_tag
      - listitem [ref=e33]:
        - link "Media" [ref=e34] [cursor=pointer]:
          - /url: upload.php
          - generic [ref=e35]: 
          - generic [ref=e36]: Media
        - list [ref=e37]:
          - listitem [ref=e38]:
            - link "Library" [ref=e39] [cursor=pointer]:
              - /url: upload.php
          - listitem [ref=e40]:
            - link "Add Media File" [ref=e41] [cursor=pointer]:
              - /url: media-new.php
      - listitem [ref=e42]:
        - link "Pages" [ref=e43] [cursor=pointer]:
          - /url: edit.php?post_type=page
          - generic [ref=e44]: 
          - generic [ref=e45]: Pages
        - list [ref=e46]:
          - listitem [ref=e47]:
            - link "All Pages" [ref=e48] [cursor=pointer]:
              - /url: edit.php?post_type=page
          - listitem [ref=e49]:
            - link "Add Page" [ref=e50] [cursor=pointer]:
              - /url: post-new.php?post_type=page
      - listitem [ref=e51]:
        - link "Comments" [ref=e52] [cursor=pointer]:
          - /url: edit-comments.php
          - generic [ref=e53]: 
          - generic [ref=e54]: Comments
      - listitem [ref=e55]
      - listitem [ref=e57]:
        - link "Appearance" [ref=e58] [cursor=pointer]:
          - /url: themes.php
          - generic [ref=e59]: 
          - generic [ref=e60]: Appearance
        - list [ref=e61]:
          - listitem [ref=e62]:
            - link "Themes" [ref=e63] [cursor=pointer]:
              - /url: themes.php
          - listitem [ref=e64]:
            - link "Editor" [ref=e65] [cursor=pointer]:
              - /url: site-editor.php
      - listitem [ref=e66]:
        - link "Plugins 1" [ref=e67] [cursor=pointer]:
          - /url: plugins.php
          - generic [ref=e68]: 
          - generic [ref=e69]:
            - text: Plugins
            - generic [ref=e70]: "1"
        - list [ref=e71]:
          - listitem [ref=e72]:
            - link "Installed Plugins" [ref=e73] [cursor=pointer]:
              - /url: plugins.php
          - listitem [ref=e74]:
            - link "Add Plugin" [ref=e75] [cursor=pointer]:
              - /url: plugin-install.php
      - listitem [ref=e76]:
        - link "Users" [ref=e77] [cursor=pointer]:
          - /url: users.php
          - generic [ref=e78]: 
          - generic [ref=e79]: Users
        - list [ref=e80]:
          - listitem [ref=e81]:
            - link "All Users" [ref=e82] [cursor=pointer]:
              - /url: users.php
          - listitem [ref=e83]:
            - link "Add User" [ref=e84] [cursor=pointer]:
              - /url: user-new.php
          - listitem [ref=e85]:
            - link "Profile" [ref=e86] [cursor=pointer]:
              - /url: profile.php
      - listitem [ref=e87]:
        - link "Tools" [ref=e88] [cursor=pointer]:
          - /url: tools.php
          - generic [ref=e89]: 
          - generic [ref=e90]: Tools
        - list [ref=e91]:
          - listitem [ref=e92]:
            - link "Available Tools" [ref=e93] [cursor=pointer]:
              - /url: tools.php
          - listitem [ref=e94]:
            - link "Import" [ref=e95] [cursor=pointer]:
              - /url: import.php
          - listitem [ref=e96]:
            - link "Export" [ref=e97] [cursor=pointer]:
              - /url: export.php
          - listitem [ref=e98]:
            - link "Site Health" [ref=e99] [cursor=pointer]:
              - /url: site-health.php
          - listitem [ref=e100]:
            - link "Export Personal Data" [ref=e101] [cursor=pointer]:
              - /url: export-personal-data.php
          - listitem [ref=e102]:
            - link "Erase Personal Data" [ref=e103] [cursor=pointer]:
              - /url: erase-personal-data.php
          - listitem [ref=e104]:
            - link "Theme File Editor" [ref=e105] [cursor=pointer]:
              - /url: theme-editor.php
          - listitem [ref=e106]:
            - link "Plugin File Editor" [ref=e107] [cursor=pointer]:
              - /url: plugin-editor.php
      - listitem [ref=e108]:
        - link "Settings" [ref=e109] [cursor=pointer]:
          - /url: options-general.php
          - generic [ref=e110]: 
          - generic [ref=e111]: Settings
        - list [ref=e112]:
          - listitem [ref=e113]:
            - link "General" [ref=e114] [cursor=pointer]:
              - /url: options-general.php
          - listitem [ref=e115]:
            - link "Writing" [ref=e116] [cursor=pointer]:
              - /url: options-writing.php
          - listitem [ref=e117]:
            - link "Reading" [ref=e118] [cursor=pointer]:
              - /url: options-reading.php
          - listitem [ref=e119]:
            - link "Discussion" [ref=e120] [cursor=pointer]:
              - /url: options-discussion.php
          - listitem [ref=e121]:
            - link "Media" [ref=e122] [cursor=pointer]:
              - /url: options-media.php
          - listitem [ref=e123]:
            - link "Permalinks" [ref=e124] [cursor=pointer]:
              - /url: options-permalink.php
          - listitem [ref=e125]:
            - link "Privacy" [ref=e126] [cursor=pointer]:
              - /url: options-privacy.php
          - listitem [ref=e127]:
            - link "Pantheon Page Cache" [ref=e128] [cursor=pointer]:
              - /url: options-general.php?page=pantheon-cache
      - listitem [ref=e129]:
        - link "Ash Nazg" [ref=e130] [cursor=pointer]:
          - /url: admin.php?page=ash-nazg
          - generic [ref=e131]: 
          - generic [ref=e132]: Ash Nazg
        - list [ref=e133]:
          - listitem [ref=e134]:
            - link "Ash Nazg" [ref=e135] [cursor=pointer]:
              - /url: admin.php?page=ash-nazg
          - listitem [ref=e136]:
            - link "Addons" [ref=e137] [cursor=pointer]:
              - /url: admin.php?page=ash-nazg-addons
          - listitem [ref=e138]:
            - link "Workflows" [ref=e139] [cursor=pointer]:
              - /url: admin.php?page=ash-nazg-workflows
          - listitem [ref=e140]:
            - link "Development" [ref=e141] [cursor=pointer]:
              - /url: admin.php?page=ash-nazg-development
          - listitem [ref=e142]:
            - link "Backups" [ref=e143] [cursor=pointer]:
              - /url: admin.php?page=ash-nazg-backups
          - listitem [ref=e144]:
            - link "Metrics" [ref=e145] [cursor=pointer]:
              - /url: admin.php?page=ash-nazg-metrics
          - listitem [ref=e146]:
            - link "Clone" [ref=e147] [cursor=pointer]:
              - /url: admin.php?page=ash-nazg-clone
          - listitem [ref=e148]:
            - link "Logs" [ref=e149] [cursor=pointer]:
              - /url: admin.php?page=ash-nazg-logs
          - listitem [ref=e150]:
            - link "Settings" [ref=e151] [cursor=pointer]:
              - /url: admin.php?page=ash-nazg-settings
      - listitem [ref=e152]:
        - button "Collapse Main menu" [expanded] [ref=e153] [cursor=pointer]:
          - generic [ref=e155]: Collapse Menu
  - generic [ref=e156]:
    - generic [ref=e157]:
      - navigation "Toolbar":
        - menu:
          - group [ref=e158]:
            - menuitem "About WordPress" [ref=e159] [cursor=pointer]:
              - generic [ref=e161]: About WordPress
          - group [ref=e162]:
            - menuitem "cxr-ash-nazg" [ref=e163] [cursor=pointer]
          - group [ref=e164]:
            - menuitem "1 update available" [ref=e165] [cursor=pointer]:
              - generic [ref=e167]: "1"
              - generic [ref=e168]: 1 update available
          - group [ref=e169]:
            - menuitem "0 Comments in moderation" [ref=e170] [cursor=pointer]:
              - generic [ref=e172]: "0"
              - generic [ref=e173]: 0 Comments in moderation
          - group [ref=e174]:
            - menuitem "New" [ref=e175] [cursor=pointer]:
              - generic [ref=e177]: New
        - menu [ref=e178]:
          - group [ref=e179]:
            - menuitem "Howdy, chris" [ref=e180] [cursor=pointer]
    - main [ref=e181]:
      - generic [ref=e183]:
        - generic [ref=e184]:
          - img "Pantheon" [ref=e186]
          - heading "Debug Logs" [level=1] [ref=e187]
          - generic [ref=e188]:
            - paragraph [ref=e189]:
              - strong [ref=e190]: "Migrate to Per-User Tokens:"
              - text: A site-wide machine token was found. Starting with version 0.4.0, Ash Nazg supports per-user machine tokens for better security and audit trails. Would you like to migrate the existing token to your user account?
            - paragraph [ref=e191]:
              - link "Migrate to Me" [ref=e192] [cursor=pointer]:
                - /url: https://e2e-3-cxr-ash-nazg.pantheonsite.io/wp-admin/admin.php?page=ash-nazg&ash_nazg_migrate=me&_wpnonce=3d6e6d9ebf
              - link "Remind me in 24 hours" [ref=e193] [cursor=pointer]:
                - /url: https://e2e-3-cxr-ash-nazg.pantheonsite.io/wp-admin/admin.php?page=ash-nazg&ash_nazg_migrate=dismiss&_wpnonce=3d6e6d9ebf
            - button "Dismiss this notice." [ref=e194] [cursor=pointer]:
              - generic [ref=e195]: Dismiss this notice.
        - generic [ref=e197]:
          - heading "WordPress Debug Logs" [level=2] [ref=e198]
          - paragraph [ref=e199]: View the contents of your WordPress debug.log file. If the site is in Git mode, it will temporarily switch to SFTP mode to read the file, then switch back.
          - button " Fetch Logs" [disabled] [ref=e200]:
            - generic [ref=e201]: 
            - text: Fetch Logs
          - button " Clear Log" [disabled] [ref=e202]:
            - generic [ref=e203]: 
            - text: Clear Log
          - paragraph [ref=e205]:
            - emphasis [ref=e207]: Fetching logs... This may take a moment if we need to switch connection modes.
  - contentinfo [ref=e208]:
    - paragraph [ref=e209]:
      - generic [ref=e210]:
        - text: Thank you for creating with
        - link "WordPress" [ref=e211] [cursor=pointer]:
          - /url: https://wordpress.org/
        - text: .
    - paragraph [ref=e212]: Version 6.9.4
```

# Test source

```ts
  1  | /**
  2  |  * Logs page user journey tests.
  3  |  */
  4  | const { expect } = require('@playwright/test');
  5  | const { test, goToPluginPage } = require('./fixtures/admin-page');
  6  | 
  7  | test.describe('Logs', () => {
  8  |   test.beforeEach(async ({ page }) => {
  9  |     await goToPluginPage(page, 'ash-nazg-logs');
  10 |     await page.waitForLoadState('networkidle');
  11 |   });
  12 | 
  13 |   test('page renders without errors', async ({ page }) => {
  14 |     await expect(page.locator('.wrap')).toBeVisible();
  15 |     await expect(page.locator('.notice-error')).toHaveCount(0);
  16 |   });
  17 | 
  18 |   test('Fetch Logs button is present and clickable', async ({ page }) => {
  19 |     const fetchBtn = page.locator('button, input[type="submit"]').filter({ hasText: /fetch logs/i });
  20 |     await expect(fetchBtn).toBeVisible({ timeout: 10_000 });
  21 |     await expect(fetchBtn).toBeEnabled();
  22 |   });
  23 | 
  24 |   test('Clear Logs button is present', async ({ page }) => {
  25 |     const clearBtn = page.locator('button, input[type="submit"]').filter({ hasText: /clear logs/i });
  26 |     await expect(clearBtn).toBeVisible({ timeout: 10_000 });
  27 |   });
  28 | 
  29 |   test('fetching logs loads content', async ({ page }) => {
  30 |     const fetchBtn = page.locator('button, input[type="submit"]').filter({ hasText: /fetch logs/i });
  31 |     await fetchBtn.click();
  32 | 
  33 |     // After fetching, either log content or a "no logs" message should appear.
  34 |     const logContent = page.locator('#ash-nazg-log-content, .ash-nazg-logs-content');
> 35 |     await expect(logContent).toBeVisible({ timeout: 15_000 });
     |                              ^ Error: expect(locator).toBeVisible() failed
  36 |   });
  37 | });
  38 | 
```