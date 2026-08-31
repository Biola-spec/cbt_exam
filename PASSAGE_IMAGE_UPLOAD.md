# Passage Image Upload Feature

## Overview
This feature allows teachers to upload images directly into comprehension passages, with immediate visual feedback similar to the school logo upload functionality.

## How It Works

### 1. Image Upload Process
1. Teacher clicks the "Upload Image" button in the passage editor
2. Selects an image file from their computer (JPG, PNG, GIF, WebP)
3. Image is uploaded to the server and stored in the `uploads/` directory
4. Image is immediately inserted into the passage content with a visual border for identification
5. Teacher can continue editing the passage and save when finished

### 2. Technical Implementation

#### Frontend (teacher/question_management.php)
- Uses a file input element hidden from view
- Button triggers the file selection dialog
- Shows visual feedback during upload (spinner animation)
- Inserts image URL directly into CKEditor content
- Provides success/error notifications

#### Backend (upload_image.php)
- Validates file type and size
- Generates unique filename to prevent conflicts
- Stores image in `uploads/` directory
- Returns JSON response with image URL

#### Display (student/take_exam.php)
- Images are displayed directly in the passage content
- Proper sanitization prevents XSS attacks
- Responsive sizing ensures images fit well on all devices

## Key Features

### Immediate Visual Feedback
- Spinner animation during upload
- Success message when upload completes
- Visual border around newly inserted images
- Button state management (disabled during upload)

### Security Measures
- File type validation (JPG, PNG, GIF, WebP only)
- File size limit (5MB maximum)
- Unique filename generation
- HTML sanitization for display

### User Experience
- Simple one-click upload process
- Support for CKEditor integration
- Visual indication of uploaded images
- Error handling with user-friendly messages

## Supported Image Formats
- JPEG (.jpg, .jpeg)
- PNG (.png)
- GIF (.gif)
- WebP (.webp)

## File Storage
- Images are stored in the `uploads/` directory
- Filenames are generated with unique identifiers
- Old images are not automatically deleted (manual cleanup may be needed)

## Usage Instructions

### For Teachers
1. Navigate to "My Exams" in the teacher dashboard
2. Select an exam, class, and subject
3. Scroll to the "Comprehension Passages" section
4. Click "Upload Image" button
5. Select an image file from your computer
6. Wait for upload to complete (spinner will show)
7. Image will appear in the passage content with a dashed border
8. Continue editing and save the passage when finished

### For Students
- Images will display automatically in comprehension passages
- No special action needed
- Images are responsive and will resize for different screen sizes

## Troubleshooting

### Upload Issues
1. Check file size (must be under 5MB)
2. Verify file format (JPG, PNG, GIF, WebP only)
3. Ensure uploads directory has write permissions
4. Check PHP upload limits in php.ini

### Display Issues
1. Verify image URL is correct in passage content
2. Check browser console for errors
3. Ensure uploads directory is accessible

## Best Practices
1. Use appropriately sized images for web display
2. Add descriptive alt text for accessibility
3. Test passages in student view after adding images
4. Periodically clean up unused images from uploads directory