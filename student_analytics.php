<?php
// Database Class - Defined at the top level
class StudentDB {
    private $host = "localhost";
    private $db_name = "student_analytics";
    private $username = "root";
    private $password = "";
    public $conn;
    
    public function connect() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return true;
        } catch(PDOException $exception) {
            error_log("Database connection failed: " . $exception->getMessage());
            return false;
        }
    }
    
    public function initDatabase() {
        try {
            // Create database if not exists
            $pdo = new PDO("mysql:host=" . $this->host, $this->username, $this->password);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS " . $this->db_name);
            $pdo = null;
            
            // Connect to the database
            if (!$this->connect()) {
                return false;
            }
            
            // Create table
            $query = "CREATE TABLE IF NOT EXISTS students (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL UNIQUE,
                course VARCHAR(50) NOT NULL,
                grade INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            
            $this->conn->exec($query);
            
            // Insert sample data if table is empty
            $check = $this->conn->query("SELECT COUNT(*) FROM students")->fetchColumn();
            if ($check == 0) {
                $sampleData = [
                    ['John Smith', 'john.smith@example.com', 'Computer Science', 85],
                    ['Emma Johnson', 'emma.johnson@example.com', 'Mathematics', 92],
                    ['Michael Brown', 'michael.brown@example.com', 'Physics', 78],
                    ['Sarah Davis', 'sarah.davis@example.com', 'Engineering', 88],
                    ['David Wilson', 'david.wilson@example.com', 'Business', 76],
                    ['Lisa Miller', 'lisa.miller@example.com', 'Computer Science', 95],
                    ['James Taylor', 'james.taylor@example.com', 'Mathematics', 82],
                    ['Jennifer Anderson', 'jennifer.anderson@example.com', 'Physics', 89]
                ];
                
                $stmt = $this->conn->prepare("INSERT INTO students (name, email, course, grade) VALUES (?, ?, ?, ?)");
                foreach ($sampleData as $data) {
                    $stmt->execute($data);
                }
            }
            return true;
        } catch (Exception $e) {
            error_log("Database init failed: " . $e->getMessage());
            return false;
        }
    }
    
    private function createStudent() {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $course = $_POST['course'] ?? '';
        $grade = $_POST['grade'] ?? '';
        
        if (empty($name) || empty($email) || empty($course) || empty($grade)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            return;
        }
        
        try {
            $stmt = $this->conn->prepare("INSERT INTO students (name, email, course, grade) VALUES (?, ?, ?, ?)");
            $success = $stmt->execute([$name, $email, $course, $grade]);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Student created successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create student']);
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                echo json_encode(['success' => false, 'message' => 'Email already exists']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
        }
    }
    
    private function readStudents() {
        $search = $_POST['search'] ?? '';
        
        try {
            if (!empty($search)) {
                $stmt = $this->conn->prepare("SELECT * FROM students WHERE name LIKE ? OR email LIKE ? OR course LIKE ? ORDER BY id DESC");
                $searchTerm = "%$search%";
                $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
            } else {
                $stmt = $this->conn->query("SELECT * FROM students ORDER BY id DESC");
            }
            
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'students' => $students]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch students: ' . $e->getMessage()]);
        }
    }
    
    private function updateStudent() {
        $id = $_POST['id'] ?? '';
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $course = $_POST['course'] ?? '';
        $grade = $_POST['grade'] ?? '';
        
        if (empty($id) || empty($name) || empty($email) || empty($course) || empty($grade)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            return;
        }
        
        try {
            $stmt = $this->conn->prepare("UPDATE students SET name=?, email=?, course=?, grade=? WHERE id=?");
            $success = $stmt->execute([$name, $email, $course, $grade, $id]);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Student updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update student']);
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                echo json_encode(['success' => false, 'message' => 'Email already exists']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
        }
    }
    
    private function deleteStudent() {
        $id = $_POST['id'] ?? '';
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'Student ID is required']);
            return;
        }
        
        try {
            $stmt = $this->conn->prepare("DELETE FROM students WHERE id=?");
            $success = $stmt->execute([$id]);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Student deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete student']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }
    
    private function getAnalytics() {
        try {
            $analytics = [];
            
            // Top performing students (above average) - USING SUBQUERY
            $stmt = $this->conn->query("SELECT name, grade FROM students WHERE grade > (SELECT AVG(grade) FROM students) ORDER BY grade DESC");
            $analytics['top_students'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Course performance ranking - USING SUBQUERY
            $stmt = $this->conn->query("SELECT course, 
                (SELECT AVG(grade) FROM students s2 WHERE s2.course = s1.course) as avg_grade,
                (SELECT COUNT(*) FROM students s3 WHERE s3.course = s1.course) as student_count
                FROM students s1 
                GROUP BY course 
                ORDER BY avg_grade DESC");
            $analytics['course_ranking'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Grade distribution - USING SUBQUERY
            $stmt = $this->conn->query("SELECT 
                (SELECT COUNT(*) FROM students WHERE grade >= 90) as A,
                (SELECT COUNT(*) FROM students WHERE grade >= 80 AND grade < 90) as B,
                (SELECT COUNT(*) FROM students WHERE grade >= 70 AND grade < 80) as C,
                (SELECT COUNT(*) FROM students WHERE grade >= 60 AND grade < 70) as D,
                (SELECT COUNT(*) FROM students WHERE grade < 60) as F");
            $analytics['grade_distribution'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'analytics' => $analytics]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch analytics: ' . $e->getMessage()]);
        }
    }
    
    public function handleRequest() {
        if (!$this->connect()) {
            echo json_encode(['success' => false, 'message' => 'Database connection failed. Check if MySQL is running.']);
            return;
        }
        
        switch ($_POST['action']) {
            case 'create':
                $this->createStudent();
                break;
            case 'read':
                $this->readStudents();
                break;
            case 'update':
                $this->updateStudent();
                break;
            case 'delete':
                $this->deleteStudent();
                break;
            case 'analytics':
                $this->getAnalytics();
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    }
}

// Handle API requests first before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $db = new StudentDB();
    $db->handleRequest();
    exit; // Stop execution here for API requests
}

// If not an API request, continue with HTML output and initialize database
$db = new StudentDB();
$initResult = $db->initDatabase();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Analytics System</title>
    <style>
        :root {
            --primary: #3498db;
            --secondary: #2c3e50;
            --success: #2ecc71;
            --danger: #e74c3c;
            --warning: #f39c12;
            --light: #ecf0f1;
            --dark: #34495e;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: var(--dark);
            line-height: 1.6;
        }
        
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 20px 0;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        header h1 {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        header p {
            text-align: center;
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .nav-tabs {
            display: flex;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .nav-tab {
            flex: 1;
            text-align: center;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            color: var(--dark);
        }
        
        .nav-tab:hover {
            background-color: var(--light);
        }
        
        .nav-tab.active {
            background-color: var(--primary);
            color: white;
        }
        
        .tab-content {
            display: none;
            background-color: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .tab-content.active {
            display: block;
        }
        
        h2 {
            color: var(--secondary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        input, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            transition: border 0.3s;
        }
        
        input:focus, select:focus {
            border-color: var(--primary);
            outline: none;
        }
        
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .btn-success {
            background-color: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #27ae60;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: var(--light);
            font-weight: 600;
        }
        
        tr:hover {
            background-color: #f9f9f9;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
        }
        
        .edit-btn {
            background-color: var(--warning);
            color: white;
        }
        
        .delete-btn {
            background-color: var(--danger);
            color: white;
        }
        
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .analytics-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .analytics-card h3 {
            color: var(--secondary);
            margin-bottom: 15px;
            font-size: 1.2rem;
        }
        
        .analytics-list {
            list-style-type: none;
        }
        
        .analytics-list li {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .analytics-list li:last-child {
            border-bottom: none;
        }
        
        .highlight {
            font-weight: 600;
            color: var(--primary);
        }
        
        .footer {
            text-align: center;
            margin-top: 40px;
            padding: 20px;
            color: var(--dark);
            font-size: 0.9rem;
            border-top: 1px solid #eee;
        }
        
        .loading {
            text-align: center;
            padding: 20px;
            color: var(--primary);
        }
        
        .error {
            background-color: #ffeaea;
            color: var(--danger);
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        .success {
            background-color: #eaffea;
            color: var(--success);
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        @media (max-width: 768px) {
            .nav-tabs {
                flex-direction: column;
            }
            
            .analytics-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>Student Analytics System</h1>
            <p>CRUD Application with SQL Subqueries for Data Insights</p>
        </div>
    </header>
    
    <div class="container">
        <?php if (!$initResult): ?>
            <div class="error">
                <strong>Database Setup Failed:</strong> Please check if MySQL is running and your database credentials are correct.
            </div>
        <?php endif; ?>
        
        <div class="nav-tabs">
            <div class="nav-tab active" data-tab="students">Manage Students</div>
            <div class="nav-tab" data-tab="analytics">Data Analytics</div>
            <div class="nav-tab" data-tab="add-student">Add New Student</div>
        </div>
        
        <!-- Students Tab -->
        <div id="students" class="tab-content active">
            <h2>Student Records</h2>
            <div class="form-group">
                <input type="text" id="searchStudent" placeholder="Search students by name, email, or course..." onkeyup="searchStudents()">
            </div>
            <div id="studentsLoading" class="loading">Loading students...</div>
            <div id="studentsError" class="error" style="display: none;"></div>
            <table id="studentsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Course</th>
                        <th>Grade</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="studentsTableBody">
                    <!-- Students will be populated by JavaScript -->
                </tbody>
            </table>
        </div>
        
        <!-- Analytics Tab -->
        <div id="analytics" class="tab-content">
            <h2>Data Analytics with SQL Subqueries</h2>
            <div id="analyticsLoading" class="loading">Loading analytics...</div>
            <div id="analyticsError" class="error" style="display: none;"></div>
            <div class="analytics-grid">
                <div class="analytics-card">
                    <h3>Top Performing Students</h3>
                    <p>Students with grades above average (using SQL subquery)</p>
                    <ul class="analytics-list" id="topStudents">
                        <!-- Will be populated by JavaScript -->
                    </ul>
                </div>
                
                <div class="analytics-card">
                    <h3>Course Performance Ranking</h3>
                    <p>Average grade by course (using SQL subqueries)</p>
                    <ul class="analytics-list" id="courseRanking">
                        <!-- Will be populated by JavaScript -->
                    </ul>
                </div>
                
                <div class="analytics-card">
                    <h3>Grade Distribution</h3>
                    <p>Students by grade category (using SQL subqueries)</p>
                    <ul class="analytics-list" id="gradeDistribution">
                        <!-- Will be populated by JavaScript -->
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Add Student Tab -->
        <div id="add-student" class="tab-content">
            <h2>Add New Student</h2>
            <form id="studentForm">
                <div class="form-group">
                    <label for="studentName">Full Name</label>
                    <input type="text" id="studentName" required>
                </div>
                
                <div class="form-group">
                    <label for="studentEmail">Email Address</label>
                    <input type="email" id="studentEmail" required>
                </div>
                
                <div class="form-group">
                    <label for="studentCourse">Course</label>
                    <select id="studentCourse" required>
                        <option value="">Select a course</option>
                        <option value="Computer Science">Computer Science</option>
                        <option value="Mathematics">Mathematics</option>
                        <option value="Physics">Physics</option>
                        <option value="Engineering">Engineering</option>
                        <option value="Business">Business</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="studentGrade">Grade (0-100)</label>
                    <input type="number" id="studentGrade" min="0" max="100" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Add Student</button>
                <div id="formMessage" style="margin-top: 15px;"></div>
            </form>
        </div>
    </div>
    
    <div class="footer">
        <div class="container">
            <p>Student Analytics System &copy; 2025 | Deadline: 10/21/25</p>
        </div>
    </div>

    <script>
        // Tab navigation
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                // Remove active class from all tabs and contents
                document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked tab and corresponding content
                tab.classList.add('active');
                document.getElementById(tab.dataset.tab).classList.add('active');
                
                // Load data when switching to analytics tab
                if (tab.dataset.tab === 'analytics') {
                    loadAnalytics();
                }
            });
        });

        // Display students in the table
        function displayStudents(students) {
            const tbody = document.getElementById('studentsTableBody');
            tbody.innerHTML = '';
            
            if (students.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">No students found</td></tr>';
                return;
            }
            
            students.forEach(student => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${student.id}</td>
                    <td>${student.name}</td>
                    <td>${student.email}</td>
                    <td>${student.course}</td>
                    <td>${student.grade}</td>
                    <td class="action-buttons">
                        <button class="action-btn edit-btn" onclick="editStudent(${student.id})">Edit</button>
                        <button class="action-btn delete-btn" onclick="deleteStudent(${student.id})">Delete</button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        // Load students from backend
        async function loadStudents() {
            try {
                document.getElementById('studentsLoading').style.display = 'block';
                document.getElementById('studentsError').style.display = 'none';
                
                const formData = new FormData();
                formData.append('action', 'read');
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                // Check if response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    throw new Error('Server returned HTML instead of JSON. Check for PHP errors.');
                }
                
                const data = await response.json();
                
                if (data.success) {
                    displayStudents(data.students);
                } else {
                    throw new Error(data.message || 'Failed to load students');
                }
            } catch (error) {
                document.getElementById('studentsError').textContent = error.message;
                document.getElementById('studentsError').style.display = 'block';
                console.error('Error loading students:', error);
            } finally {
                document.getElementById('studentsLoading').style.display = 'none';
            }
        }

        // Search students
        async function searchStudents() {
            const searchTerm = document.getElementById('searchStudent').value.trim();
            
            try {
                document.getElementById('studentsLoading').style.display = 'block';
                
                const formData = new FormData();
                formData.append('action', 'read');
                formData.append('search', searchTerm);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    displayStudents(data.students);
                } else {
                    throw new Error(data.message || 'Failed to search students');
                }
            } catch (error) {
                console.error('Error searching students:', error);
            } finally {
                document.getElementById('studentsLoading').style.display = 'none';
            }
        }

        // Add new student
        document.getElementById('studentForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const name = document.getElementById('studentName').value;
            const email = document.getElementById('studentEmail').value;
            const course = document.getElementById('studentCourse').value;
            const grade = parseInt(document.getElementById('studentGrade').value);
            
            try {
                const formData = new FormData();
                formData.append('action', 'create');
                formData.append('name', name);
                formData.append('email', email);
                formData.append('course', course);
                formData.append('grade', grade);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Reset form
                    this.reset();
                    document.getElementById('formMessage').innerHTML = 
                        '<div class="success">' + data.message + '</div>';
                    
                    // Reload students
                    loadStudents();
                    
                    // Clear message after 3 seconds
                    setTimeout(() => {
                        document.getElementById('formMessage').innerHTML = '';
                    }, 3000);
                } else {
                    throw new Error(data.message || 'Failed to add student');
                }
            } catch (error) {
                document.getElementById('formMessage').innerHTML = 
                    '<div class="error">Error: ' + error.message + '</div>';
                console.error('Error adding student:', error);
            }
        });

        // Edit student
        async function editStudent(id) {
            try {
                // First, get the current student data
                const formData = new FormData();
                formData.append('action', 'read');
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message || 'Failed to load student data');
                }
                
                const student = data.students.find(s => s.id === id);
                if (!student) {
                    throw new Error('Student not found');
                }
                
                const newName = prompt('Enter new name:', student.name);
                if (newName === null) return;
                
                const newEmail = prompt('Enter new email:', student.email);
                if (newEmail === null) return;
                
                const newCourse = prompt('Enter new course:', student.course);
                if (newCourse === null) return;
                
                const newGrade = parseInt(prompt('Enter new grade (0-100):', student.grade));
                if (isNaN(newGrade) || newGrade < 0 || newGrade > 100) {
                    alert('Invalid grade entered');
                    return;
                }
                
                // Update student via backend
                const updateFormData = new FormData();
                updateFormData.append('action', 'update');
                updateFormData.append('id', id);
                updateFormData.append('name', newName);
                updateFormData.append('email', newEmail);
                updateFormData.append('course', newCourse);
                updateFormData.append('grade', newGrade);
                
                const updateResponse = await fetch('', {
                    method: 'POST',
                    body: updateFormData
                });
                
                const updateData = await updateResponse.json();
                
                if (updateData.success) {
                    alert(updateData.message);
                    loadStudents();
                    loadAnalytics(); // Refresh analytics
                } else {
                    throw new Error(updateData.message || 'Failed to update student');
                }
            } catch (error) {
                alert('Error: ' + error.message);
                console.error('Error updating student:', error);
            }
        }

        // Delete student
        async function deleteStudent(id) {
            if (!confirm('Are you sure you want to delete this student?')) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert(data.message);
                    loadStudents();
                    loadAnalytics(); // Refresh analytics
                } else {
                    throw new Error(data.message || 'Failed to delete student');
                }
            } catch (error) {
                alert('Error: ' + error.message);
                console.error('Error deleting student:', error);
            }
        }

        // Load analytics
        async function loadAnalytics() {
            try {
                document.getElementById('analyticsLoading').style.display = 'block';
                document.getElementById('analyticsError').style.display = 'none';
                
                const formData = new FormData();
                formData.append('action', 'analytics');
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    updateAnalyticsDisplay(data.analytics);
                } else {
                    throw new Error(data.message || 'Failed to load analytics');
                }
            } catch (error) {
                document.getElementById('analyticsError').textContent = error.message;
                document.getElementById('analyticsError').style.display = 'block';
                console.error('Error loading analytics:', error);
            } finally {
                document.getElementById('analyticsLoading').style.display = 'none';
            }
        }

        // Update analytics display
        function updateAnalyticsDisplay(analytics) {
            // Top performing students
            const topStudentsList = document.getElementById('topStudents');
            topStudentsList.innerHTML = '';
            
            if (analytics.top_students && analytics.top_students.length > 0) {
                analytics.top_students.forEach(student => {
                    const li = document.createElement('li');
                    li.innerHTML = `${student.name} - <span class="highlight">${student.grade}</span>`;
                    topStudentsList.appendChild(li);
                });
            } else {
                topStudentsList.innerHTML = '<li>No top performing students found</li>';
            }
            
            // Course performance ranking
            const courseRankingList = document.getElementById('courseRanking');
            courseRankingList.innerHTML = '';
            
            if (analytics.course_ranking && analytics.course_ranking.length > 0) {
                analytics.course_ranking.forEach(course => {
                    const li = document.createElement('li');
                    li.innerHTML = `${course.course} - <span class="highlight">${parseFloat(course.avg_grade).toFixed(1)}</span> (${course.student_count} students)`;
                    courseRankingList.appendChild(li);
                });
            } else {
                courseRankingList.innerHTML = '<li>No course data available</li>';
            }
            
            // Grade distribution
            const gradeDistributionList = document.getElementById('gradeDistribution');
            gradeDistributionList.innerHTML = '';
            
            if (analytics.grade_distribution) {
                const grades = analytics.grade_distribution;
                const items = [
                    { label: 'A (90-100)', count: grades.A },
                    { label: 'B (80-89)', count: grades.B },
                    { label: 'C (70-79)', count: grades.C },
                    { label: 'D (60-69)', count: grades.D },
                    { label: 'F (0-59)', count: grades.F }
                ];
                
                items.forEach(item => {
                    const li = document.createElement('li');
                    li.innerHTML = `${item.label}: <span class="highlight">${item.count} students</span>`;
                    gradeDistributionList.appendChild(li);
                });
            } else {
                gradeDistributionList.innerHTML = '<li>No grade distribution data available</li>';
            }
        }

        // Initialize the application
        function init() {
            loadStudents();
        }

        // Run initialization when page loads
        document.addEventListener('DOMContentLoaded', init);
    </script>
</body>
</html>