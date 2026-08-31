# 🚀 Quick Start Guide - CKEditor 5 Image Upload

## ⚡ Get Started in 3 Steps

### **Step 1: Enable GD Library (Required)**

**Check if GD is enabled:**
```
http://localhost/cbt_exam/check_gd_status.php
```

**If NOT enabled:**
1. Open XAMPP Control Panel
2. Config → PHP (php.ini)
3. Search for: `;extension=gd`
4. Remove semicolon: `extension=gd`
5. Save file
6. Restart Apache

---

### **Step 2: Verify Uploads Folder**

The uploads folder should already exist and be writable. If not:

**Windows (PowerShell as Administrator):**
```powershell
icacls "C:\xampp\htdocs\cbt_exam\uploads" /grant Users:F /T
```

**Verify .htaccess exists:**
- Check: `uploads/.htaccess` (should block PHP execution)

---

### **Step 3: Test Image Upload**

1. **Navigate to Question Management:**
   ```
   http://localhost/cbt_exam/teacher/question_management.php?exam_id=2&class_id=1&subject_id=11
   ```

2. **Scroll to Comprehension Passages section**

3. **Try uploading an image using one of these methods:**

   **Method A: Upload Button**
   - Click the 📷 Image icon in toolbar
   - Select an image file
   - Wait for upload to complete
   
   **Method B: Drag & Drop**
   - Drag an image from your computer
   - Drop it into the editor
   
   **Method C: Paste**
   - Copy any image (Ctrl+C)
   - Click in editor
   - Paste (Ctrl+V)

4. **Add some text and save the passage**

5. **Verify image displays correctly**

---

## ✅ Success Indicators

You'll know it's working when you see:

✅ CKEditor toolbar with formatting options  
✅ Image icon (📷) in toolbar  
✅ Upload progress bar appears  
✅ Image displays in editor after upload  
✅ No error messages  
✅ Image saves with passage to database  

---

## ❌ Troubleshooting Common Issues

### **"Failed to upload image"**
**Fix:** Run diagnostic page
```
http://localhost/cbt_exam/ensure_uploads.php
```

### **"GD Library not enabled"**
**Fix:** Follow Step 1 above to enable GD

### **Editor not loading**
**Fix:** Check browser console (F12) for errors
- Ensure internet connection (loading from CDN)
- Check for JavaScript conflicts

### **Images not displaying after save**
**Fix:** Verify database content
```sql
SELECT passage_content FROM comprehension_passages WHERE id = YOUR_PASSAGE_ID;
```
Should show HTML with `<img src="uploads/...">` tags

---

## 🎯 What You Get

With this implementation, you can now:

✅ **Upload images** via button, drag-drop, or paste  
✅ **Automatic optimization** - Images converted to WebP format  
✅ **Responsive display** - Works on all screen sizes  
✅ **Secure storage** - PHP execution blocked in uploads  
✅ **Multiple images** per passage  
✅ **Image captions** and alt text support  
✅ **Professional editing** with rich text formatting  

---

## 📁 Files Overview

### **Core Files:**
```
cbt_exam/
├── teacher/
│   ├── upload_image.php          ← Handles image uploads
│   └── question_management.php   ← Contains CKEditor
├── includes/
│   └── ckeditor_setup.php        ← Editor configuration
├── uploads/
│   └── .htaccess                 ← Security rules
└── assets/css/
    └── ckeditor-custom.css       ← (Optional) Custom styles
```

### **How It Works:**

1. **Teacher clicks image icon** → CKEditor opens file picker
2. **File selected** → Sent to `upload_image.php`
3. **PHP validates & optimizes** → Converts to WebP, resizes
4. **Saves to uploads/** → Returns URL: `uploads/filename.webp`
5. **CKEditor inserts** → `<img src="uploads/filename.webp">`
6. **Form submits** → HTML saved to database
7. **Student views** → Images display from database

---

## 🔧 Advanced Configuration

### **Change File Size Limit:**
Edit `teacher/upload_image.php`:
```php
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // Change to 5MB
```

### **Change Image Dimensions:**
Edit `teacher/upload_image.php`:
```php
define('IMAGE_MAX_WIDTH', 1200); // Change max width
```

### **Disable WebP Conversion:**
Edit `teacher/upload_image.php`:
```php
// Comment out the optimizeImage() call
// if ($mimeType !== 'image/gif') {
//     optimizeImage($targetPath, $targetPath, $mimeType);
// }
```

---

## 📊 Performance Tips

### **For Best Results:**

1. **Source Images:**
   - Use JPEG for photos (smaller files)
   - Use PNG for diagrams (sharp edges)
   - Avoid BMP/TIFF (huge files)

2. **Recommended Sizes:**
   - Photos: 800-1200px wide
   - Diagrams: Any size (lossless)
   - Max file size: 2MB (enforced)

3. **Storage Management:**
   - Regular cleanup of unused images
   - Database backup includes images
   - Consider cloud storage for large scale

---

## 🎓 Next Steps

Now that it's working:

1. ✅ **Test thoroughly** with different image types
2. ✅ **Train teachers** on using the editor
3. ✅ **Set guidelines** for image sizes/types
4. ✅ **Monitor storage** usage
5. ✅ **Backup regularly**

---

## 📞 Support Resources

### **Diagnostic Tools:**
- `check_gd_status.php` - Check GD library
- `ensure_uploads.php` - Verify setup
- `quick_upload_test.php` - Test uploads

### **Documentation:**
- `CKEDITOR5_COMPLETE_SOLUTION.md` - Full documentation
- `ENABLE_GD_LIBRARY.md` - GD setup guide
- This file (`QUICK_START.md`) - Quick reference

---

## ✨ That's It!

You're ready to use CKEditor 5 with professional image uploads! 

**No configuration needed** - Just start adding images to your exam passages! 🎉

---

**Last Updated:** March 24, 2026  
**Version:** 1.0  
**Status:** Production Ready ✅
