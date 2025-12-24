# AIMatic Plugin Release & Update Guide

This guide explains how to release new versions of the AIMatic Writer plugin and how to switch the update source from GitHub to your own server.

## Part 1: How to Release a New Version

Follow these steps whenever you want to push an update to your users.

### 1. Update the Plugin Version
Open `aimatic-writer.php` and increment the version number at the top of the file:
```php
/*
Plugin Name: AIMatic Writer
...
Version: 1.1.3  <-- Change this (e.g., from 1.1.2 to 1.1.3)
...
*/
```

### 2. Create the Zip File
Compress the entire `aimatic-write-plugin` folder into a `.zip` file.
*   **Name:** `aimatic-writer.zip` (Standard naming is best).

### 3. Upload to Hosting (GitHub Method)
1.  Go to your GitHub Repository: `https://github.com/xudevstudio/AIMatic-Plugin`
2.  Click **"Releases"** on the right sidebar.
3.  Click **"Draft a new release"**.
4.  **Tag version:** `v1.1.3` (Must match your plugin version).
5.  **Title:** `Version 1.1.3`
6.  **Attach binaries:** Drag and drop your `aimatic-writer.zip` here.
7.  Click **Publish release**.
8.  **Copy the Download Link:** Right-click the `.zip` file you just attached and copy the link (e.g., `https://github.com/.../releases/download/v1.1.3/aimatic-writer.zip`).

### 4. Update the JSON File
Open the `update.json` file in your repository code (main branch). Update it with the new details:

```json
{
    "name": "AIMatic Writer",
    "slug": "aimatic-writer",
    "version": "1.1.3",  <-- MUST match the new version
    "download_url": "PASTE_YOUR_COPIED_ZIP_LINK_HERE",
    "sections": {
        "changelog": "<h4>1.1.3</h4><ul><li>Fixed bugs.</li><li>Added new feature.</li></ul>"
    }
}
```
**Commit and Push** this change to your repository.

**Done!** Within 12-24 hours (or when they click "Check for Updates"), users will see the update in their WordPress dashboard.

---

## Part 2: Switching to Your Own Server

When you are ready to stop using GitHub and host the updates yourself (e.g., `https://aimaticwriter.com/updates/`), follow these steps.

### 1. Host the Files
1.  Upload `aimatic-writer.zip` to your server.
2.  Upload `update.json` to your server.
3.  Ensure `update.json` has the correct `download_url` pointing to your server's zip file.

### 2. Update the Plugin Code
Open `aimatic-writer.php` in your plugin code.

**Find this line (approx line 34):**
```php
define('AIMATIC_UPDATE_URL', 'https://raw.githubusercontent.com/xudevstudio/AIMatic-Plugin/main/update.json');
```

**Change it to your server URL:**
```php
define('AIMATIC_UPDATE_URL', 'https://your-domain.com/path/to/update.json');
```

### 3. Release this Update
Follow the steps in **Part 1** to release this code change as a new version (e.g., 1.2.0).
*   **Important:** You must release this "Switch" update via the *OLD* method (GitHub) first.
*   Once users update to 1.2.0, their plugin will start looking at your **new server** for version 1.2.1 and beyond.
