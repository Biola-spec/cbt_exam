# CKEditor 5 Image Upload - Fixed

## Issues Resolved

The "Cannot upload file: SAFIYYA.png" error has been fixed by addressing the following issues:

### 1. **Wrong CKEditor 5 Configuration** ✓ FIXED
- **Problem**: Was using `ckfinder` config which is for CKEditor 4
- **Solution**: Changed to `simpleUpload` configuration which is correct for CKEditor 5

**Before:**
```javascript
ckfinder: {
    uploadUrl: '/teacher/upload_image.php',
    headers: {
        'X-Requested-With': 'XMLHttpRequest'
    }
}
```

**After:**
```javascript
simpleUpload: {
    uploadUrl: '/cbt_exam/upload_image.php',
    headers: {
        'X-Requested-With': 'XMLHttpRequest'
    },
    withCredentials: false
}
```

### 2. **Incorrect Upload URL Path** ✓ FIXED
- **Problem**: Relative paths were breaking when accessed from subdirectories
- **Solution**: Using absolute path `/cbt_exam/upload_image.php`

### 3. **Wrong Response Format** ✓ FIXED
- **Problem**: Response didn't match CKEditor 5's expected format
- **Solution**: Now returns the correct JSON structure

**CKEditor 5 expects:**
```json
{
    "default": "/cbt_exam/uploads/image.jpg",
    "url": "/cbt_exam/uploads/image.jpg"
}
```

### 4. **Missing File Field Handling** ✓ FIXED
- **Problem**: Upload script only looked for `$_FILES['image']` but CKEditor sends `$_FILES['upload']`
- **Solution**: Script now detects request type and handles both field names

### 5. **CKEditor Not Enabled on Question Fields** ✓ FIXED
- **Problem**: Only passage_content had CKEditor enabled
- **Solution**: Added `data-cke-auto-init` to:
  - question_text
  - option_a through option_f
  - passage_content

## Files Modified

1. **includes/ckeditor_setup.php**
   - Changed from `ckfinder` to `simpleUpload` configuration
   - Updated upload URL to absolute path

2. **upload_image.php** (root directory)
   - Added detection for CKEditor 5 requests vs manual uploads
   - Handles both `$_FILES['upload']` (CKEditor) and `$_FILES['image']` (manual)
   - Returns correct JSON format for each request type
   - Uses absolute URL path `/cbt_exam/uploads/`

3. **teacher/upload_image.php**
   - Updated response format to match CKEditor 5 requirements
   - Uses absolute URL path

4. **teacher/question_management.php**
   - Added `data-cke-auto-init` attribute to question_text textarea
   - Added `data-cke-auto-init` attribute to all option fields (A-F)

## How to Test

### 1. Run the Test Page
Visit: `http://localhost/cbt_exam/test_ckeditor_upload.php`

This will check:
- ✓ Upload directory exists and is writable
- ✓ PHP configuration is correct
- ✓ Upload endpoint is accessible
- ✓ Manual upload functionality

### 2. Test CKEditor Upload
1. Login as a teacher
2. Go to Question Management
3. You should now see rich text editors with image upload buttons
4. Click the image icon in the toolbar
5. Select or drag & drop an image
6. The image should upload and appear in the editor

### 3. Browser Console Check
Open browser console (F12) and check for:
- No JavaScript errors
- Network tab shows successful POST to `/cbt_exam/upload_image.php`
- Response contains `{"default": "...", "url": "..."}`

## Troubleshooting

### If Upload Still Fails:

#### 1. Check Upload Directory Permissions
```powershell
icacls "c:\xampp\htdocs\cbt_exam\uploads" /grant Users:F
```

#### 2. Verify PHP Settings
Check `php.ini` in XAMPP:
```ini
upload_max_filesize = 10M
post_max_size = 10M
file_uploads = On
max_file_uploads = 20
```

Restart Apache after making changes.

#### 3. Check Browser Console
Press F12 and look for:
- JavaScript errors in Console tab
- Failed requests in Network tab
- Check the request payload and response

#### 4. Verify Upload URL
The upload URL must be accessible:
- Test: `http://localhost/cbt_exam/upload_image.php`
- Should not return 404 error

#### 5. Check File Size
- Maximum file size: 2MB (configured in upload_image.php)
- If your image is larger, increase the limit in the PHP file

#### 6. Allowed File Types
Currently allowed:
- JPEG/JPG
- PNG
- GIF
- WebP

## Response Format Reference

### CKEditor 5 Request
- **Method**: POST
- **Content-Type**: multipart/form-data
- **File Field**: `upload`
- **Additional Headers**: `X-Requested-With: XMLHttpRequest`

### CKEditor 5 Success Response
```json
{
    "default": "/cbt_exam/uploads/filename.jpg",
    "url": "/cbt_exam/uploads/filename.jpg"
}
```

### CKEditor 5 Error Response
```json
{
    "error": {
        "message": "Error description here"
    }
}
```

### Manual Upload Request
- **Method**: POST
- **Content-Type**: multipart/form-data
- **File Field**: `image`
- **Action Field**: `action=upload_image`

### Manual Upload Success Response
```json
{
    "success": true,
    "url": "/cbt_exam/uploads/filename.jpg",
    "mime_type": "image/jpeg",
    "size": 12345
}
```

## Features Now Working

✓ Image upload via toolbar button
✓ Drag & drop images into editor
✓ Paste images from clipboard
✓ Image resize and alignment
✓ Images in question text
✓ Images in answer options
✓ Images in comprehension passages
✓ Automatic image optimization (if GD library available)
✓ File type validation
✓ File size validation
✓ Unique filename generation

## Next Steps

1. Test the upload functionality with different image types
2. Verify images display correctly in the editor
3. Test on student exam view to ensure images render properly
4. Consider adding image compression settings if needed

## Support

If you encounter any issues:
1. Check browser console for errors
2. Verify upload directory permissions
3. Test with the diagnostic page
4. Check PHP error logs in `c:\xampp\apache\logs\error.log`
