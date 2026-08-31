# How to Enable GD Library in XAMPP

## The Problem
Your upload is failing because the **GD library** is not enabled in PHP.
This library is required for image processing and compression.

---

## Quick Fix (3 Steps)

### **Step 1: Open php.ini**
1. Open **XAMPP Control Panel**
2. Click **Config** button for Apache
3. Select **PHP (php.ini)** from the dropdown menu

   OR manually open: `C:\xampp\php\php.ini`

---

### **Step 2: Enable GD Extension**
1. Press **Ctrl + F** to search for: `;extension=gd`
2. You'll find this line (with a semicolon at the start):
   ```ini
   ;extension=gd
   ```
3. **Remove the semicolon** to make it:
   ```ini
   extension=gd
   ```
4. Save the file (Ctrl + S)

---

### **Step 3: Restart Apache**
1. Go back to **XAMPP Control Panel**
2. Click **Stop** for Apache
3. Wait 2 seconds
4. Click **Start** for Apache

---

## Verify It Worked

Open your browser and go to:
```
http://localhost/cbt_exam/ensure_uploads.php
```

You should now see:
- ✅ **GD Library:** Enabled (green checkmark)

Then try uploading MARYAM.jpg again in CKEditor!

---

## Alternative: If GD Line Doesn't Exist

If you can't find `;extension=gd`, add this line at the end of the extensions section:

```ini
[PHP_GD]
extension=gd
```

---

## Still Not Working?

### Check if GD DLL exists:
1. Navigate to: `C:\xampp\php\ext\`
2. Look for: `php_gd.dll`
3. If missing, you may need to reinstall XAMPP or PHP

### Check Apache error log:
1. XAMPP Control Panel → Apache Logs → Error Log
2. Look for GD-related errors

---

## What Changed in Your Upload Script

I've already updated `upload_image.php` to:
- ✅ Be more lenient with MIME type detection
- ✅ Use file extension as fallback
- ✅ Suppress warnings that could break JSON
- ✅ Handle cases where GD is not available
- ✅ Skip compression if GD fails and just save the file directly

So once GD is enabled, uploads should work perfectly!

---

## After Enabling GD

Test the upload:
1. Go to: `http://localhost/cbt_exam/teacher/question_management.php`
2. Scroll to Comprehension Passages
3. Click the image icon in CKEditor toolbar 📷
4. Select MARYAM.jpg
5. It should upload successfully! ✅
