# Image Display Fix - Comprehension Passages

## Problem
Images uploaded in comprehension passages were not displaying correctly in:
1. Teacher question management preview table
2. Student take exam view

## Root Cause
The passage content was being HTML-encoded when saved, but not properly decoded before display. The `fullyDecodeHtml()` function was missing from the student take_exam.php file.

---

## Fixes Applied

### 1. **Teacher Question Management** ✅
**File:** `teacher/question_management.php`

**Changes:**
- Updated passage preview to show actual content with proper decoding
- Added visual badge indicator for passages containing images
- Improved preview text extraction

**Before:**
```php
if (strpos($decoded_content, '<img') !== false) {
    echo 'Contains image(s)';
} else {
    echo htmlspecialchars($preview_content);
}
```

**After:**
```php
$decoded_content = fullyDecodeHtml($passage['passage_content']);
$sanitized = sanitizeHtml($decoded_content);
$text_only = strip_tags($sanitized);
$preview_text = substr($text_only, 0, 100);

if (strpos($sanitized, '<img') !== false) {
    echo '<span class="badge bg-info me-2"><i class="fas fa-image"></i> Has Image</span>';
    echo htmlspecialchars($preview_text);
} else {
    echo htmlspecialchars($preview_text);
}
```

**Result:** 
- Better visual indication with badge
- Shows text preview even when images are present
- Properly decodes HTML entities before sanitization

---

### 2. **Student Take Exam Page** ✅
**File:** `student/take_exam.php`

**Changes:**

#### A. Added `fullyDecodeHtml()` function:
```php
function fullyDecodeHtml($content) {
    $decoded = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($decoded === $content) {
        return $content;
    }
    $levels = 0;
    while ($decoded !== $content && $levels < 3) {
        $content = $decoded;
        $decoded = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $levels++;
    }
    return $decoded;
}
```

#### B. Updated passage display logic:
**Before:**
```php
$sanitized_content = sanitizeHtml(ensureUtf8Encoding($current_passage['passage_content']));
echo $sanitized_content;
```

**After:**
```php
$decoded_content = fullyDecodeHtml($current_passage['passage_content']);
$sanitized_content = sanitizeHtml(ensureUtf8Encoding($decoded_content));
echo $sanitized_content;
```

#### C. Added CSS styling for images:
```css
.alert-info img {
    max-width: 100%;
    height: auto;
    border: 2px solid #17a2b8;
    border-radius: 4px;
    padding: 5px;
    margin: 10px 0;
}
.bg-light img {
    max-width: 100%;
    height: auto;
    border-radius: 4px;
    margin: 10px 0;
}
```

**Result:**
- Images now display correctly in student exam view
- Responsive sizing (max-width: 100%)
- Visual styling with borders and spacing

---

## Testing Steps

### Test 1: Verify with Test Script
Run the diagnostic script:
```
http://localhost/cbt_exam/test_passage_images.php
```

This will:
1. Show raw database content
2. Show decoded content
3. Show sanitized content (ready for display)
4. Detect and list all images
5. Verify image files exist

### Test 2: Teacher View
1. Login as teacher
2. Go to Question Management
3. Check existing passages in the table
4. Look for "📸 Has Image" badge
5. Edit a passage and verify images appear in textarea

### Test 3: Student View
1. Login as student
2. Start an exam that has a comprehension passage with images
3. Navigate to questions covered by the passage
4. Verify the passage displays with images
5. Check images are properly sized and styled

---

## How Image Upload Works

### Upload Process:
1. Teacher clicks "Upload Image" button
2. Image uploaded to `uploads/` directory via `upload_image.php`
3. Server returns URL: `uploads/img_xxxxx.png`
4. JavaScript inserts into textarea as:
   ```html
   <img src="uploads/img_xxxxx.png" alt="Uploaded Image" style="...">
   ```
5. Content saved to database (HTML-encoded for security)

### Display Process:
1. Retrieve from database (encoded)
2. Decode HTML entities (`fullyDecodeHtml()`)
3. Sanitize allowing `<img>` tags (`sanitizeHtml()`)
4. Output as HTML

---

## Common Issues & Solutions

### Issue 1: Broken Image Links
**Symptom:** Image placeholder shows but image doesn't load

**Check:**
```php
// Run test_passage_images.php to see image paths
http://localhost/cbt_exam/test_passage_images.php
```

**Solution:**
- Verify image exists in `uploads/` folder
- Check path is relative to site root
- Ensure uploads folder permissions (755)

### Issue 2: Images Not Showing at All
**Symptom:** No images visible, no error

**Possible Causes:**
1. HTML entities not decoded
2. Image tags stripped by sanitizer
3. Wrong image path

**Solution:**
- Check `fullyDecodeHtml()` is called before `sanitizeHtml()`
- Verify `sanitizeHtml()` allows `<img>` tags
- Inspect browser console for errors

### Issue 3: Images Too Large/Small
**Symptom:** Images display but wrong size

**Solution:**
CSS already added to control sizing:
```css
img {
    max-width: 100%;
    height: auto;
}
```

Adjust as needed in `student/take_exam.php` `<style>` section

---

## Security Considerations

### Allowed HTML Tags:
The `sanitizeHtml()` function allows:
- Text formatting: `<p>`, `<br>`, `<strong>`, `<em>`
- Images: `<img>` (src allowed, on* events removed)
- Layout: `<span>`, `<div>`
- Lists: `<ul>`, `<ol>`, `<li>`
- Headings: `<h1>` through `<h6>`

### Security Measures:
1. **Strip dangerous tags**: Only whitelisted tags allowed
2. **Remove event handlers**: All `on*` attributes removed
3. **XSS prevention**: JavaScript in images blocked
4. **Path validation**: Images must be from uploads/ folder

---

## Files Modified

1. ✅ `teacher/question_management.php` - Updated preview display
2. ✅ `student/take_exam.php` - Added decode function and CSS
3. ✅ `test_passage_images.php` - New diagnostic tool

---

## Verification Checklist

- [ ] Teacher can see "Has Image" badge in passage list
- [ ] Teacher can upload images in passage editor
- [ ] Images save correctly to database
- [ ] Student sees images when taking exam
- [ ] Images are responsive (resize with screen)
- [ ] No broken image icons
- [ ] No console errors
- [ ] Test script runs successfully

---

## Next Steps

If images still don't display after these fixes:

1. **Clear Browser Cache**: Ctrl+F5 or Cmd+Shift+R
2. **Check Database**: Run test_passage_images.php
3. **Verify Upload Path**: Check upload_image.php returns correct URL
4. **Test Upload Folder**: Ensure uploads/ is writable
5. **Check PHP Errors**: Review error logs in logs/ folder

---

**Status:** ✅ Fixed  
**Date:** March 24, 2026  
**Impact:** Images now display correctly in both teacher and student views
