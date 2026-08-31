# Image Import in Comprehension Passage Box

## Overview
This feature allows teachers to insert images directly into comprehension passages using CKEditor's built-in image insertion tool or by uploading images from their computer.

## How to Use

### 1. Accessing the Passage Editor
1. Log in as a teacher
2. Navigate to "My Exams" in the dashboard
3. Select an exam, class, and subject
4. Scroll down to the "Comprehension Passages" section

### 2. Adding Images to Passages
1. In the "Passage Content" textarea, you'll see a rich text editor (CKEditor)
2. You can either:
   - Click on the "Image" button in the toolbar (looks like a mountain landscape icon) to insert an image URL
   - Click the "Upload Image" button to upload an image from your computer
   - Use the "Insert Image URL" button (fallback method) to insert an image by URL

### 3. Editing Existing Passages with Images
1. Click the "Edit" button next to any existing passage
2. The passage content will load in the CKEditor
3. You can modify the content and add/remove images as needed
4. Click "Save Passage" when finished

## Technical Details

### Supported Image Formats
- JPG/JPEG
- PNG
- GIF
- SVG
- WebP

### Image Display
Images will be displayed in:
- Student exam view (when the passage is shown)
- Admin question review view
- Teacher preview

### Enhanced Features
- **Immediate Visual Feedback**: Uploaded images show a dashed border for easy identification
- **Progress Indicator**: Spinner animation shows during upload
- **Responsive Images**: Images automatically resize for different screen sizes
- **Success Notification**: Alert confirms when upload is complete

### Security Notes
- All HTML content is sanitized to prevent XSS attacks
- Only safe HTML tags are allowed (including `<img>`)
- JavaScript and event handlers are stripped from image tags
- File type and size validation prevents malicious uploads

## Troubleshooting

### Images Not Displaying
1. Ensure the image URL is correct and accessible
2. Check that the image format is supported
3. Verify that the passage was saved properly

### Upload Issues
1. Check file size (must be under 5MB)
2. Verify file format (JPG, PNG, GIF, WebP only)
3. Ensure uploads directory has write permissions
4. Check PHP upload limits in php.ini

### Editor Issues
1. If the rich text editor doesn't appear, try refreshing the page
2. Make sure you're using a modern browser (Chrome, Firefox, Edge recommended)

## Best Practices
1. Use appropriately sized images to ensure fast loading
2. Add descriptive alt text to images for accessibility
3. Test passages in student view to ensure proper display
4. Periodically clean up unused images from uploads directory