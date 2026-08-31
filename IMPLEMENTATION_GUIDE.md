# 🎓 Complete Implementation Guide - Class Filtering & Gender Features

## ✅ FEATURES TO IMPLEMENT:

1. **Filter students by class in admin view results**
2. **Filter subjects by classes when viewing**  
3. **Add gender field to student registration**
4. **Show boys/girls count in class view**

---

## 📋 STEP 1: Run Database Migration

Open this URL to add the gender column:
```
http://localhost/cbt_exam/add_gender_column.php
```

This will add `gender ENUM('male', 'female')` to the users table.

---

## 📋 STEP 2: Files to Modify

### **File 1: admin/student_management.php**

**Changes needed:**
1. Add gender field to add student form (line ~325)
2. Add gender field to edit modal (line ~428)
3. Update INSERT query to include gender
4. Update UPDATE query to include gender
5. Display gender badge in student cards

**Code changes:**

#### A. Add gender dropdown after class selection (around line 325):
```php
<div class="col-md-4">
    <div class="mb-3">
        <label class="form-label" for="class_id">Class</label>
        <select class="form-select" id="class_id" name="class_id" <?php echo !$has_class_id || empty($classes) ? 'disabled' : ''; ?> required>
            <option value="">Select Class</option>
            <?php foreach ($classes as $c): ?>
                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
<div class="col-md-4">
    <div class="mb-3">
        <label class="form-label" for="gender">Gender</label>
        <select class="form-select" id="gender" name="gender" required>
            <option value="">Select Gender</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
        </select>
    </div>
</div>
```

#### B. Update INSERT handler (around line 66-94):
```php
$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$gender = $_POST['gender'] ?? '';
$class_id = intval($_POST['class_id'] ?? 0);

// ... validations ...
elseif (empty($gender)) {
    $message = 'Please select gender.';
} else {
    // ...
    $ins = $mysqli->prepare("INSERT INTO users (email, password, full_name, gender, role, class_id) VALUES (?, ?, ?, ?, 'student', ?)");
    if ($ins) {
        $ins->bind_param('ssssi', $email, $hash, $full_name, $gender, $class_id);
        // ...
    }
}
```

#### C. Update UPDATE handler (around line 117-150):
```php
$sid = intval($_POST['student_id'] ?? 0);
$full_name = trim($_POST['edit_full_name'] ?? '');
$email = trim($_POST['edit_email'] ?? '');
$gender = $_POST['edit_gender'] ?? '';
$class_id = intval($_POST['edit_class_id'] ?? 0);

// ... validations ...
if ($has_class_id) {
    $upd = $mysqli->prepare("UPDATE users SET full_name = ?, email = ?, gender = ?, class_id = ? WHERE id = ? AND role = 'student'");
    $upd->bind_param('sssii', $full_name, $email, $gender, $class_id, $sid);
}
```

#### D. Add gender to student cards display (around line 363):
```php
<?php if ($has_class_id): ?>
    <p class="card-text small mb-2">
        <span class="badge bg-info small"><?php echo htmlspecialchars($s['class_name'] ?? 'No class assigned'); ?></span>
        <?php if (!empty($s['gender'])): ?>
            <span class="badge bg-<?php echo $s['gender'] === 'male' ? 'primary' : 'danger'; ?> small ms-1">
                <?php echo ucfirst($s['gender']); ?>
            </span>
        <?php endif; ?>
    </p>
<?php endif; ?>
```

#### E. Update edit modal to include gender (around line 428):
```php
<?php if ($has_class_id): ?>
<div class="mb-3">
    <label class="form-label" for="edit_class_id">Class</label>
    <select class="form-select" name="edit_class_id" id="edit_class_id" required>
        <option value="">Select Class</option>
        <?php foreach ($classes as $c): ?>
            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="mb-3">
    <label class="form-label" for="edit_gender">Gender</label>
    <select class="form-select" name="edit_gender" id="edit_gender" required>
        <option value="">Select Gender</option>
        <option value="male" <?php echo isset($s['gender']) && $s['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
        <option value="female" <?php echo isset($s['gender']) && $s['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
    </select>
</div>
<?php endif; ?>
```

#### F. Update modal JavaScript to load gender (around line 450):
```javascript
modal.addEventListener('show.bs.modal', function (event) {
  var button = event.relatedTarget;
  var id = button.getAttribute('data-id');
  var name = button.getAttribute('data-name');
  var email = button.getAttribute('data-email');
  var classId = button.getAttribute('data-class-id');
  var gender = button.getAttribute('data-gender');
  
  document.getElementById('edit_student_id').value = id;
  document.getElementById('edit_full_name').value = name;
  document.getElementById('edit_email').value = email;
  
  if (classId) {
      document.getElementById('edit_class_id').value = classId;
  }
  
  if (gender) {
      document.getElementById('edit_gender').value = gender;
  }
});
```

---

### **File 2: admin/view_all_results.php**

**Changes needed:**
1. Filter students dropdown based on selected class
2. Use AJAX to dynamically load students when class changes

Add this JavaScript before closing body tag:
```javascript
<script>
// Dynamic student filtering by class
document.addEventListener('DOMContentLoaded', function() {
    const classSelect = document.querySelector('select[name="class_id"]');
    const studentSelect = document.querySelector('select[name="student_id"]');
    
    if (classSelect && studentSelect) {
        classSelect.addEventListener('change', function() {
            const classId = this.value;
            
            // Clear current options
            studentSelect.innerHTML = '<option value="">All Students</option>';
            
            if (classId) {
                // Fetch students for selected class
                fetch(`get_students_by_class.php?class_id=${classId}`)
                    .then(response => response.json())
                    .then(students => {
                        students.forEach(student => {
                            const option = document.createElement('option');
                            option.value = student.id;
                            option.textContent = student.full_name;
                            studentSelect.appendChild(option);
                        });
                    });
            }
        });
    }
});
</script>
```

Create new file `admin/get_students_by_class.php`:
```php
<?php
require_once '../config/database.php';
header('Content-Type: application/json');

$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$students = [];

if ($class_id > 0) {
    $mysqli = getDBConnection();
    $stmt = $mysqli->prepare("SELECT id, full_name FROM users WHERE role = 'student' AND class_id = ? ORDER BY full_name");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    
    $stmt->close();
}

echo json_encode($students);
?>
```

---

### **File 3: admin/class_management.php**

**Changes needed:**
Add gender statistics (boys/girls count) to each class card.

Find where classes are displayed (around line 150-250) and add:

```php
<?php 
// Get gender statistics for this class
$gender_stats = ['male' => 0, 'female' => 0];
if ($has_class_id) {
    $stmt = $mysqli->prepare("SELECT gender, COUNT(*) as count FROM users WHERE role = 'student' AND class_id = ? AND gender IS NOT NULL GROUP BY gender");
    $stmt->bind_param("i", $class['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $gender_stats[$row['gender']] = $row['count'];
    }
    $stmt->close();
}
?>

<!-- In the class card -->
<div class="card mb-3 shadow-sm">
    <div class="card-body">
        <h5 class="card-title"><?php echo htmlspecialchars($class['name']); ?></h5>
        <p class="card-text"><?php echo htmlspecialchars($class['description']); ?></p>
        
        <!-- Gender Statistics -->
        <div class="row text-center mt-3">
            <div class="col-6">
                <div class="p-2 bg-primary text-white rounded">
                    <i class="fas fa-mars me-1"></i>
                    <strong><?php echo $gender_stats['male']; ?></strong> Boys
                </div>
            </div>
            <div class="col-6">
                <div class="p-2 bg-danger text-white rounded">
                    <i class="fas fa-venus me-1"></i>
                    <strong><?php echo $gender_stats['female']; ?></strong> Girls
                </div>
            </div>
        </div>
        
        <div class="mt-2 text-muted small">
            Total Students: <?php echo array_sum($gender_stats); ?>
        </div>
    </div>
</div>
```

---

## 🧪 TESTING CHECKLIST

After implementing all changes:

### Test 1: Student Registration
- [ ] Go to admin/student_management.php
- [ ] Add new student with gender
- [ ] Verify gender badge appears on student card

### Test 2: Edit Student
- [ ] Click "Edit" on a student
- [ ] Modal should show gender dropdown
- [ ] Change gender and save
- [ ] Verify update worked

### Test 3: View Results Filtering
- [ ] Go to admin/view_all_results.php
- [ ] Select a class from dropdown
- [ ] Student dropdown should update automatically
- [ ] Only show students from that class

### Test 4: Class Gender Stats
- [ ] Go to admin/class_management.php
- [ ] Each class card should show boys/girls count
- [ ] Counts should be accurate

---

## 🚀 QUICK START

1. **Run migration:**
   ```
   http://localhost/cbt_exam/add_gender_column.php
   ```

2. **Update files** using the code snippets above

3. **Test all features**

---

## 📁 FILES AFFECTED

1. ✅ `add_gender_column.php` (NEW) - Database migration
2. ✏️ `admin/student_management.php` - Add/edit gender
3. ✏️ `admin/view_all_results.php` - Filter students by class
4. ✏️ `admin/class_management.php` - Show gender stats
5. ✅ `admin/get_students_by_class.php` (NEW) - AJAX endpoint

---

## 💡 NOTES

- All existing students without gender will show NULL
- Gender is required for new students
- Gender dropdown uses Bootstrap styling
- AJAX requires JavaScript enabled
- Gender stats update in real-time

---

**Status:** Ready to implement  
**Difficulty:** Medium  
**Estimated Time:** 30-45 minutes
