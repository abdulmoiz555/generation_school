-- ========================================================
-- School Management System Database Schema & Seed Data
-- Database: school_management
-- Supported: MySQL 5.7+ / MySQL 8.0+ / MariaDB 10.3+
-- ========================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS notices;
DROP TABLE IF EXISTS assignments;
DROP TABLE IF EXISTS payroll;
DROP TABLE IF EXISTS salary_structures;
DROP TABLE IF EXISTS employee_leaves;
DROP TABLE IF EXISTS leave_types;
DROP TABLE IF EXISTS employee_attendance;
DROP TABLE IF EXISTS employees;
DROP TABLE IF EXISTS stock_transactions;
DROP TABLE IF EXISTS purchase_items;
DROP TABLE IF EXISTS purchases;
DROP TABLE IF EXISTS inventory_items;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS inventory_categories;
DROP TABLE IF EXISTS student_transport;
DROP TABLE IF EXISTS transport_routes;
DROP TABLE IF EXISTS vehicles;
DROP TABLE IF EXISTS drivers;
DROP TABLE IF EXISTS book_issues;
DROP TABLE IF EXISTS books;
DROP TABLE IF EXISTS authors;
DROP TABLE IF EXISTS book_categories;
DROP TABLE IF EXISTS marks;
DROP TABLE IF EXISTS grades;
DROP TABLE IF EXISTS exam_subjects;
DROP TABLE IF EXISTS exams;
DROP TABLE IF EXISTS exam_types;
DROP TABLE IF EXISTS expenses;
DROP TABLE IF EXISTS expense_categories;
DROP TABLE IF EXISTS fee_payment_items;
DROP TABLE IF EXISTS fee_payments;
DROP TABLE IF EXISTS student_fees;
DROP TABLE IF EXISTS fee_discounts;
DROP TABLE IF EXISTS fee_structures;
DROP TABLE IF EXISTS fee_types;
DROP TABLE IF EXISTS teacher_attendance;
DROP TABLE IF EXISTS student_attendance;
DROP TABLE IF EXISTS timetables;
DROP TABLE IF EXISTS teacher_subjects;
DROP TABLE IF EXISTS class_subjects;
DROP TABLE IF EXISTS subjects;
DROP TABLE IF EXISTS staff;
DROP TABLE IF EXISTS teachers;
DROP TABLE IF EXISTS designations;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS student_documents;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS parents;
DROP TABLE IF EXISTS sections;
DROP TABLE IF EXISTS classes;
DROP TABLE IF EXISTS academic_sessions;
DROP TABLE IF EXISTS school_settings;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Users Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(50) NULL,
    avatar VARCHAR(255) DEFAULT 'default-avatar.png',
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    remember_token VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_role (role_id),
    INDEX idx_user_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Roles Table
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Permissions Table
CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module VARCHAR(50) NOT NULL,
    code VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_perm_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Role Permissions Table
CREATE TABLE role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    UNIQUE KEY uq_role_perm (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. School Settings Table
CREATE TABLE school_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(100) NOT NULL UNIQUE,
    key_value TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Academic Sessions Table
CREATE TABLE academic_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_name VARCHAR(50) NOT NULL UNIQUE,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_current TINYINT(1) DEFAULT 0,
    status ENUM('active', 'inactive', 'archived') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Parents Table
CREATE TABLE parents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    father_name VARCHAR(150) NOT NULL,
    mother_name VARCHAR(150) NULL,
    guardian_name VARCHAR(150) NULL,
    cnic VARCHAR(50) NULL,
    phone VARCHAR(50) NOT NULL,
    alternate_phone VARCHAR(50) NULL,
    email VARCHAR(150) NULL,
    occupation VARCHAR(100) NULL,
    address TEXT NULL,
    city VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Classes Table
CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(100) NOT NULL,
    numeric_level INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_class_level (numeric_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Sections Table
CREATE TABLE sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    section_name VARCHAR(50) NOT NULL,
    class_teacher_id INT NULL,
    room_no VARCHAR(50) NULL,
    capacity INT DEFAULT 40,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Students Table
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    parent_id INT NULL,
    admission_no VARCHAR(50) NOT NULL UNIQUE,
    roll_no VARCHAR(50) NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    dob DATE NOT NULL,
    cnic_bform VARCHAR(50) NULL,
    phone VARCHAR(50) NULL,
    email VARCHAR(150) NULL,
    address TEXT NULL,
    city VARCHAR(100) NULL,
    province VARCHAR(100) NULL,
    admission_date DATE NOT NULL,
    session_id INT NOT NULL,
    class_id INT NOT NULL,
    section_id INT NOT NULL,
    previous_school VARCHAR(255) NULL,
    blood_group VARCHAR(10) NULL,
    photo VARCHAR(255) DEFAULT 'default-student.png',
    status ENUM('active', 'inactive', 'promoted', 'graduated', 'transferred') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE SET NULL,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id),
    FOREIGN KEY (class_id) REFERENCES classes(id),
    FOREIGN KEY (section_id) REFERENCES sections(id),
    INDEX idx_student_class_sec (class_id, section_id),
    INDEX idx_student_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Student Documents Table
CREATE TABLE student_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Departments Table
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Designations Table
CREATE TABLE designations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Teachers Table
CREATE TABLE teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    teacher_code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    dob DATE NOT NULL,
    cnic VARCHAR(50) NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    address TEXT NULL,
    qualification VARCHAR(255) NULL,
    experience_years INT DEFAULT 0,
    joining_date DATE NOT NULL,
    department_id INT NULL,
    designation_id INT NULL,
    salary DECIMAL(10, 2) DEFAULT 0.00,
    photo VARCHAR(255) DEFAULT 'default-teacher.png',
    status ENUM('active', 'inactive', 'resigned') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (designation_id) REFERENCES designations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. Staff Table
CREATE TABLE staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    staff_code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    dob DATE NOT NULL,
    cnic VARCHAR(50) NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(150) NULL,
    address TEXT NULL,
    department_id INT NULL,
    designation_id INT NULL,
    joining_date DATE NOT NULL,
    salary DECIMAL(10, 2) DEFAULT 0.00,
    photo VARCHAR(255) DEFAULT 'default-staff.png',
    status ENUM('active', 'inactive', 'resigned') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    FOREIGN KEY (designation_id) REFERENCES designations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. Subjects Table
CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_name VARCHAR(100) NOT NULL,
    subject_code VARCHAR(50) NOT NULL,
    class_id INT NOT NULL,
    max_marks INT DEFAULT 100,
    pass_marks INT DEFAULT 40,
    teacher_id INT NULL,
    subject_type ENUM('Theory', 'Practical', 'Both') DEFAULT 'Theory',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. Class Subjects Table
CREATE TABLE class_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    section_id INT NOT NULL,
    subject_id INT NOT NULL,
    teacher_id INT NULL,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. Timetables Table
CREATE TABLE timetables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    class_id INT NOT NULL,
    section_id INT NOT NULL,
    day ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') NOT NULL,
    period_number INT NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    subject_id INT NOT NULL,
    teacher_id INT NULL,
    room_no VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. Student Attendance Table
CREATE TABLE student_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    section_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('Present', 'Absent', 'Late', 'Leave') DEFAULT 'Present',
    remarks VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student_att (student_id, attendance_date),
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id),
    FOREIGN KEY (section_id) REFERENCES sections(id),
    INDEX idx_att_date_cls (attendance_date, class_id, section_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. Teacher Attendance Table
CREATE TABLE teacher_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('Present', 'Absent', 'Late', 'Leave') DEFAULT 'Present',
    remarks VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_teacher_att (teacher_id, attendance_date),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 21. Fee Types Table
CREATE TABLE fee_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    description TEXT NULL,
    is_recurring TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 22. Fee Structures Table
CREATE TABLE fee_structures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    class_id INT NOT NULL,
    fee_type_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    due_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_type_id) REFERENCES fee_types(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 23. Fee Discounts Table
CREATE TABLE fee_discounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    discount_type ENUM('Fixed', 'Percentage') DEFAULT 'Fixed',
    amount DECIMAL(10, 2) NOT NULL,
    description VARCHAR(255) NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 24. Student Fees (Invoices/Bills) Table
CREATE TABLE student_fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    student_id INT NOT NULL,
    fee_type_id INT NOT NULL,
    month VARCHAR(20) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    discount_amount DECIMAL(10, 2) DEFAULT 0.00,
    fine_amount DECIMAL(10, 2) DEFAULT 0.00,
    paid_amount DECIMAL(10, 2) DEFAULT 0.00,
    balance DECIMAL(10, 2) NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('Pending', 'Partial', 'Paid', 'Overdue') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_type_id) REFERENCES fee_types(id),
    INDEX idx_st_fee_status (student_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 25. Fee Payments Table
CREATE TABLE fee_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_no VARCHAR(50) NOT NULL UNIQUE,
    session_id INT NOT NULL,
    student_id INT NOT NULL,
    payment_date DATE NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    discount_amount DECIMAL(10, 2) DEFAULT 0.00,
    fine_amount DECIMAL(10, 2) DEFAULT 0.00,
    paid_amount DECIMAL(10, 2) NOT NULL,
    balance_remaining DECIMAL(10, 2) DEFAULT 0.00,
    payment_method ENUM('Cash', 'Bank', 'Online', 'Cheque', 'Other') DEFAULT 'Cash',
    transaction_ref VARCHAR(100) NULL,
    remarks TEXT NULL,
    received_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_receipt_date (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 26. Fee Payment Items Table
CREATE TABLE fee_payment_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    student_fee_id INT NOT NULL,
    amount_applied DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (payment_id) REFERENCES fee_payments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_fee_id) REFERENCES student_fees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 27. Expense Categories Table
CREATE TABLE expense_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 28. Expenses Table
CREATE TABLE expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    expense_date DATE NOT NULL,
    payment_method ENUM('Cash', 'Bank', 'Cheque', 'Online') DEFAULT 'Cash',
    reference_no VARCHAR(100) NULL,
    receipt_file VARCHAR(255) NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES expense_categories(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_expense_date (expense_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 29. Exam Types Table
CREATE TABLE exam_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 30. Exams Table
CREATE TABLE exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    exam_type_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('Upcoming', 'Active', 'Completed', 'Results Published') DEFAULT 'Upcoming',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id),
    FOREIGN KEY (exam_type_id) REFERENCES exam_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 31. Exam Subjects Table
CREATE TABLE exam_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    class_id INT NOT NULL,
    subject_id INT NOT NULL,
    exam_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    max_marks INT DEFAULT 100,
    pass_marks INT DEFAULT 40,
    room_no VARCHAR(50) NULL,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 32. Grades Table
CREATE TABLE grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grade_name VARCHAR(10) NOT NULL,
    min_percentage DECIMAL(5, 2) NOT NULL,
    max_percentage DECIMAL(5, 2) NOT NULL,
    grade_point DECIMAL(3, 2) DEFAULT 0.00,
    remarks VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 33. Marks Table
CREATE TABLE marks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    exam_subject_id INT NOT NULL,
    student_id INT NOT NULL,
    marks_obtained DECIMAL(5, 2) NOT NULL,
    max_marks DECIMAL(5, 2) NOT NULL,
    percentage DECIMAL(5, 2) NOT NULL,
    grade VARCHAR(10) NOT NULL,
    is_passed TINYINT(1) DEFAULT 1,
    remarks VARCHAR(255) NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_exam_subject_student (exam_subject_id, student_id),
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (exam_subject_id) REFERENCES exam_subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 34. Library Tables
CREATE TABLE book_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE authors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    isbn VARCHAR(50) NULL UNIQUE,
    title VARCHAR(200) NOT NULL,
    author_id INT NOT NULL,
    publisher VARCHAR(150) NULL,
    category_id INT NOT NULL,
    edition VARCHAR(50) NULL,
    quantity INT NOT NULL DEFAULT 1,
    available_quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10, 2) DEFAULT 0.00,
    shelf_no VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES authors(id),
    FOREIGN KEY (category_id) REFERENCES book_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE book_issues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    student_id INT NOT NULL,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE NULL,
    fine_amount DECIMAL(10, 2) DEFAULT 0.00,
    book_condition VARCHAR(100) DEFAULT 'Good',
    status ENUM('Issued', 'Returned', 'Overdue') DEFAULT 'Issued',
    issued_by INT NULL,
    returned_to INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (returned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 35. Transport Tables
CREATE TABLE drivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    cnic VARCHAR(50) NULL,
    phone VARCHAR(50) NOT NULL,
    license_no VARCHAR(100) NOT NULL,
    license_expiry DATE NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_no VARCHAR(50) NOT NULL UNIQUE,
    registration_no VARCHAR(100) NOT NULL,
    vehicle_type VARCHAR(50) NOT NULL,
    capacity INT NOT NULL,
    driver_id INT NULL,
    status ENUM('active', 'maintenance', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transport_routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_name VARCHAR(150) NOT NULL,
    pickup_point VARCHAR(200) NOT NULL,
    drop_point VARCHAR(200) NOT NULL,
    monthly_fee DECIMAL(10, 2) NOT NULL,
    vehicle_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_transport (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    route_id INT NOT NULL,
    session_id INT NOT NULL,
    start_date DATE NOT NULL,
    status ENUM('active', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (route_id) REFERENCES transport_routes(id),
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 36. Inventory Tables
CREATE TABLE inventory_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100) NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(150) NULL,
    address TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    supplier_id INT NULL,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    unit VARCHAR(50) NOT NULL,
    min_stock INT DEFAULT 5,
    current_stock INT DEFAULT 0,
    purchase_price DECIMAL(10, 2) DEFAULT 0.00,
    location VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES inventory_categories(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(100) NOT NULL UNIQUE,
    supplier_id INT NOT NULL,
    purchase_date DATE NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    payment_status ENUM('Paid', 'Partial', 'Due') DEFAULT 'Paid',
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    transaction_type ENUM('In', 'Out', 'Adjustment') NOT NULL,
    quantity INT NOT NULL,
    reference VARCHAR(100) NULL,
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 37. HR & Payroll Tables
CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    employee_code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    cnic VARCHAR(50) NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(150) NULL,
    address TEXT NULL,
    department_id INT NOT NULL,
    designation_id INT NOT NULL,
    joining_date DATE NOT NULL,
    basic_salary DECIMAL(10, 2) NOT NULL,
    status ENUM('active', 'inactive', 'resigned', 'terminated') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (designation_id) REFERENCES designations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE employee_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('Present', 'Absent', 'Late', 'Leave') DEFAULT 'Present',
    remarks VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_emp_att (employee_id, attendance_date),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leave_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    days_allowed INT NOT NULL DEFAULT 12,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE employee_leaves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days INT NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    approved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id),
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE salary_structures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL UNIQUE,
    basic_salary DECIMAL(10, 2) NOT NULL,
    medical_allowance DECIMAL(10, 2) DEFAULT 0.00,
    house_rent DECIMAL(10, 2) DEFAULT 0.00,
    transport_allowance DECIMAL(10, 2) DEFAULT 0.00,
    tax_deduction DECIMAL(10, 2) DEFAULT 0.00,
    other_deductions DECIMAL(10, 2) DEFAULT 0.00,
    net_salary DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payroll (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    month_year VARCHAR(20) NOT NULL,
    basic_salary DECIMAL(10, 2) NOT NULL,
    allowances DECIMAL(10, 2) DEFAULT 0.00,
    bonus DECIMAL(10, 2) DEFAULT 0.00,
    overtime DECIMAL(10, 2) DEFAULT 0.00,
    deductions DECIMAL(10, 2) DEFAULT 0.00,
    net_salary DECIMAL(10, 2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('Cash', 'Bank Transfer', 'Cheque') DEFAULT 'Bank Transfer',
    status ENUM('Paid', 'Pending') DEFAULT 'Paid',
    payslip_no VARCHAR(100) NOT NULL UNIQUE,
    generated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 38. Assignments Table
CREATE TABLE assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    class_id INT NOT NULL,
    section_id INT NOT NULL,
    subject_id INT NOT NULL,
    teacher_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    attachment VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id),
    FOREIGN KEY (class_id) REFERENCES classes(id),
    FOREIGN KEY (section_id) REFERENCES sections(id),
    FOREIGN KEY (subject_id) REFERENCES subjects(id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 39. Notice Board Table
CREATE TABLE notices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    date DATE NOT NULL,
    audience ENUM('Everyone', 'Students', 'Parents', 'Teachers', 'Staff') DEFAULT 'Everyone',
    attachment VARCHAR(255) NULL,
    status ENUM('Active', 'Archived') DEFAULT 'Active',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 40. Messages Table
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    subject VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 41. Notifications Table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255) NULL,
    is_read TINYINT(1) DEFAULT 0,
    type VARCHAR(50) DEFAULT 'info',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 42. Audit Logs Table
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(50) NOT NULL,
    record_id INT NULL,
    details TEXT NULL,
    ip_address VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_module (module),
    INDEX idx_audit_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- SEED DATA
-- Default Passwords for all sample users: admin123
-- Hash: $2y$10$g/qsawBtSNqvrQMTPFelD.SWCBLTx..5T.nP6H.0FX8BnFhuI4i6a
-- ========================================================

-- Roles
INSERT INTO roles (id, name, display_name, description) VALUES
(1, 'superadmin', 'Super Admin', 'Full control and access to all system modules'),
(2, 'admin', 'Admin', 'Manages students, teachers, academics, attendance, and general operations'),
(3, 'accountant', 'Accountant', 'Manages fee collection, discounts, arrears, expenses, and payroll'),
(4, 'teacher', 'Teacher', 'Manages marks, assignments, timetable, and attendance'),
(5, 'receptionist', 'Receptionist', 'Front desk admissions, inquiries, and student lookups'),
(6, 'librarian', 'Librarian', 'Manages books catalog, book issuance, returns, and fines'),
(7, 'parent', 'Parent', 'View ward academic results, fee receipts, timetable, and notices'),
(8, 'student', 'Student', 'View personal profile, attendance, result cards, and assignments');

-- Permissions
INSERT INTO permissions (id, module, code, description) VALUES
(1, 'system', 'all_access', 'Super user total system privilege'),
(2, 'students', 'student_view', 'View student directory and profiles'),
(3, 'students', 'student_add', 'Enroll new students'),
(4, 'students', 'student_edit', 'Update student profile information'),
(5, 'students', 'student_delete', 'Remove or inactivate students'),
(6, 'students', 'student_promote', 'Promote students to next class'),
(7, 'teachers', 'teacher_view', 'View teacher profiles'),
(8, 'teachers', 'teacher_add', 'Add new teachers'),
(9, 'teachers', 'teacher_edit', 'Edit teacher details'),
(10, 'teachers', 'teacher_delete', 'Delete teachers'),
(11, 'academics', 'academics_manage', 'Manage classes, sections, subjects, timetable'),
(12, 'attendance', 'attendance_student', 'Take and update student attendance'),
(13, 'attendance', 'attendance_teacher', 'Take and update teacher attendance'),
(14, 'fees', 'fees_collect', 'Collect fees and print receipts'),
(15, 'fees', 'fees_manage', 'Configure fee structures and discounts'),
(16, 'examinations', 'exam_manage', 'Create exams and schedules'),
(17, 'examinations', 'marks_entry', 'Enter and edit exam marks'),
(18, 'library', 'library_manage', 'Manage books, issues, and returns'),
(19, 'transport', 'transport_manage', 'Manage vehicles and routes'),
(20, 'inventory', 'inventory_manage', 'Manage stock and purchases'),
(21, 'hr', 'hr_manage', 'Manage employees, leave, and payroll'),
(22, 'communication', 'notices_manage', 'Create and broadcast notices'),
(23, 'reports', 'reports_view', 'Access financial, academic, and attendance reports'),
(24, 'settings', 'settings_manage', 'Configure school settings and academic sessions');

-- Super Admin gets full access
INSERT INTO role_permissions (role_id, permission_id) VALUES
(1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6), (1, 7), (1, 8), (1, 9), (1, 10),
(1, 11), (1, 12), (1, 13), (1, 14), (1, 15), (1, 16), (1, 17), (1, 18), (1, 19),
(1, 20), (1, 21), (1, 22), (1, 23), (1, 24);

-- Admin permissions
INSERT INTO role_permissions (role_id, permission_id) VALUES
(2, 2), (2, 3), (2, 4), (2, 6), (2, 7), (2, 8), (2, 9), (2, 11), (2, 12), (2, 13),
(2, 14), (2, 15), (2, 16), (2, 17), (2, 18), (2, 19), (2, 20), (2, 21), (2, 22), (2, 23);

-- Accountant permissions
INSERT INTO role_permissions (role_id, permission_id) VALUES
(3, 2), (3, 14), (3, 15), (3, 20), (3, 21), (3, 23);

-- Teacher permissions
INSERT INTO role_permissions (role_id, permission_id) VALUES
(4, 2), (4, 11), (4, 12), (4, 17), (4, 22);

-- Receptionist permissions
INSERT INTO role_permissions (role_id, permission_id) VALUES
(5, 2), (5, 3), (5, 4), (5, 14), (5, 22);

-- Librarian permissions
INSERT INTO role_permissions (role_id, permission_id) VALUES
(6, 2), (6, 18);

-- Default Users
INSERT INTO users (id, username, email, password, role_id, full_name, phone, avatar, status) VALUES
(1, 'superadmin', 'superadmin@edumanage.edu', '$2y$10$g/qsawBtSNqvrQMTPFelD.SWCBLTx..5T.nP6H.0FX8BnFhuI4i6a', 1, 'Dr. Sarah Jenkins (Super Admin)', '+1 (555) 019-2831', 'avatar-admin.png', 'active'),
(2, 'admin', 'admin@edumanage.edu', '$2y$10$g/qsawBtSNqvrQMTPFelD.SWCBLTx..5T.nP6H.0FX8BnFhuI4i6a', 2, 'Prof. Robert Vance (School Admin)', '+1 (555) 019-2832', 'avatar-admin2.png', 'active'),
(3, 'accountant', 'accountant@edumanage.edu', '$2y$10$g/qsawBtSNqvrQMTPFelD.SWCBLTx..5T.nP6H.0FX8BnFhuI4i6a', 3, 'David Sterling (Head Accountant)', '+1 (555) 019-2833', 'avatar-acc.png', 'active'),
(4, 'teacher', 'teacher@edumanage.edu', '$2y$10$g/qsawBtSNqvrQMTPFelD.SWCBLTx..5T.nP6H.0FX8BnFhuI4i6a', 4, 'Mrs. Elizabeth Bennett (Senior Teacher)', '+1 (555) 019-2834', 'avatar-teacher.png', 'active'),
(5, 'receptionist', 'receptionist@edumanage.edu', '$2y$10$g/qsawBtSNqvrQMTPFelD.SWCBLTx..5T.nP6H.0FX8BnFhuI4i6a', 5, 'Clara Oswald (Front Desk Officer)', '+1 (555) 019-2835', 'avatar-recep.png', 'active'),
(6, 'librarian', 'librarian@edumanage.edu', '$2y$10$g/qsawBtSNqvrQMTPFelD.SWCBLTx..5T.nP6H.0FX8BnFhuI4i6a', 6, 'Arthur Pendelton (Librarian)', '+1 (555) 019-2836', 'avatar-lib.png', 'active'),
(7, 'parent', 'parent@edumanage.edu', '$2y$10$g/qsawBtSNqvrQMTPFelD.SWCBLTx..5T.nP6H.0FX8BnFhuI4i6a', 7, 'Marcus Aurelius Sterling (Parent)', '+1 (555) 019-2837', 'avatar-parent.png', 'active'),
(8, 'student', 'student@edumanage.edu', '$2y$10$g/qsawBtSNqvrQMTPFelD.SWCBLTx..5T.nP6H.0FX8BnFhuI4i6a', 8, 'Alexander Sterling (Student)', '+1 (555) 019-2838', 'avatar-student.png', 'active');

-- School Settings
INSERT INTO school_settings (key_name, key_value) VALUES
('school_name', 'Generation Model School'),
('school_motto', 'Excellence in Education, Character in Leadership'),
('registration_no', 'EDU-REG-2024-9843'),
('principal_name', 'Dr. Sarah Jenkins, Ph.D.'),
('phone', '+1 (800) 555-0199'),
('email', 'info@generationmodelschool.edu'),
('website', 'https://generationmodelschool.edu'),
('address', 'Main Campus, Educational Zone'),
('city', 'Islamabad'),
('province', 'Federal'),
('country', 'Pakistan'),
('currency', 'PKR'),
('currency_symbol', 'PKR '),
('active_session_id', '1'),
('logo', '/assets/img/generation_school_logo.jpg'),
('receipt_footer_note', 'This is a computer generated official fee receipt. Thank you for your payment.');

-- Academic Sessions
INSERT INTO academic_sessions (id, session_name, start_date, end_date, is_current, status) VALUES
(1, '2025-2026', '2025-08-15', '2026-06-30', 1, 'active'),
(2, '2026-2027', '2026-08-15', '2027-06-30', 0, 'inactive');

-- Departments & Designations
INSERT INTO departments (id, name, code) VALUES
(1, 'Academic Faculty', 'ACAD'),
(2, 'Administration & Operations', 'ADMIN'),
(3, 'Finance & Accounts', 'FIN'),
(4, 'Library & Resources', 'LIB'),
(5, 'Logistics & Transport', 'TRANS');

INSERT INTO designations (id, department_id, title) VALUES
(1, 1, 'Senior Subject Specialist'),
(2, 1, 'Class Teacher'),
(3, 1, 'Junior Teacher'),
(4, 2, 'Operations Coordinator'),
(5, 3, 'Senior Accountant'),
(6, 4, 'Chief Librarian'),
(7, 5, 'Transport Supervisor');

-- Teachers
INSERT INTO teachers (id, user_id, teacher_code, name, gender, dob, cnic, phone, email, address, qualification, experience_years, joining_date, department_id, designation_id, salary, photo, status) VALUES
(1, 4, 'TCH-1001', 'Mrs. Elizabeth Bennett', 'Female', '1985-04-12', '42101-1234567-1', '+1 (555) 101-2001', 'teacher@edumanage.edu', '12 Rosewood Lane', 'M.Sc. Mathematics, B.Ed', 10, '2019-08-01', 1, 1, 4500.00, 'teacher1.png', 'active'),
(2, NULL, 'TCH-1002', 'Mr. Jonathan Edwards', 'Male', '1988-09-22', '42101-1234567-2', '+1 (555) 101-2002', 'j.edwards@edumanage.edu', '45 Elm Street', 'M.A. English Literature', 8, '2020-01-15', 1, 2, 4200.00, 'teacher2.png', 'active'),
(3, NULL, 'TCH-1003', 'Dr. Farhan Siddiqui', 'Male', '1982-11-05', '42101-1234567-3', '+1 (555) 101-2003', 'f.siddiqui@edumanage.edu', '88 University Heights', 'Ph.D. Physics', 14, '2017-09-01', 1, 1, 5200.00, 'teacher3.png', 'active'),
(4, NULL, 'TCH-1004', 'Ms. Aisha Khan', 'Female', '1992-03-18', '42101-1234567-4', '+1 (555) 101-2004', 'a.khan@edumanage.edu', '23 Pine View Rd', 'M.Sc. Computer Science', 6, '2021-08-10', 1, 2, 4000.00, 'teacher4.png', 'active'),
(5, NULL, 'TCH-1005', 'Mr. Michael Chang', 'Male', '1986-07-30', '42101-1234567-5', '+1 (555) 101-2005', 'm.chang@edumanage.edu', '9 Oakwood Drive', 'M.Sc. Chemistry', 9, '2019-09-01', 1, 2, 4300.00, 'teacher5.png', 'active');

-- Staff
INSERT INTO staff (id, user_id, staff_code, name, gender, dob, cnic, phone, email, address, department_id, designation_id, joining_date, salary, photo, status) VALUES
(1, 3, 'STF-2001', 'David Sterling', 'Male', '1984-06-15', '42101-7654321-1', '+1 (555) 202-3001', 'accountant@edumanage.edu', '17 Finance Way', 3, 5, '2018-03-01', 4800.00, 'staff1.png', 'active'),
(2, 5, 'STF-2002', 'Clara Oswald', 'Female', '1994-11-23', '42101-7654321-2', '+1 (555) 202-3002', 'receptionist@edumanage.edu', '55 Maple Avenue', 2, 4, '2022-02-15', 3200.00, 'staff2.png', 'active'),
(3, 6, 'STF-2003', 'Arthur Pendelton', 'Male', '1979-01-10', '42101-7654321-3', '+1 (555) 202-3003', 'librarian@edumanage.edu', '101 Library Court', 4, 6, '2016-09-01', 3600.00, 'staff3.png', 'active');

-- Classes
INSERT INTO classes (id, class_name, numeric_level) VALUES
(1, 'Grade 1', 1),
(2, 'Grade 2', 2),
(3, 'Grade 3', 3),
(4, 'Grade 4', 4),
(5, 'Grade 5', 5);

-- Sections
INSERT INTO sections (id, class_id, section_name, class_teacher_id, room_no, capacity) VALUES
(1, 1, 'Section A', 1, 'Room 101', 35),
(2, 1, 'Section B', 2, 'Room 102', 35),
(3, 2, 'Section A', 3, 'Room 201', 35),
(4, 2, 'Section B', 4, 'Room 202', 35),
(5, 3, 'Section A', 5, 'Room 301', 40),
(6, 3, 'Section B', 1, 'Room 302', 40),
(7, 4, 'Section A', 2, 'Room 401', 40),
(8, 4, 'Section B', 3, 'Room 402', 40),
(9, 5, 'Section A', 4, 'Room 501', 40),
(10, 5, 'Section B', 5, 'Room 502', 40);

-- Subjects
INSERT INTO subjects (id, subject_name, subject_code, class_id, max_marks, pass_marks, teacher_id, subject_type) VALUES
(1, 'Mathematics', 'MTH-101', 1, 100, 40, 1, 'Theory'),
(2, 'English Language', 'ENG-101', 1, 100, 40, 2, 'Theory'),
(3, 'General Science', 'SCI-101', 1, 100, 40, 3, 'Both'),
(4, 'Computer Studies', 'CMP-101', 1, 100, 40, 4, 'Both'),
(5, 'Social Studies', 'SST-101', 1, 100, 40, 5, 'Theory'),
(6, 'Mathematics', 'MTH-201', 2, 100, 40, 1, 'Theory'),
(7, 'English Language', 'ENG-201', 2, 100, 40, 2, 'Theory'),
(8, 'General Science', 'SCI-201', 2, 100, 40, 3, 'Both'),
(9, 'Mathematics', 'MTH-501', 5, 100, 40, 1, 'Theory'),
(10, 'English Language', 'ENG-501', 5, 100, 40, 2, 'Theory'),
(11, 'General Science', 'SCI-501', 5, 100, 40, 3, 'Both'),
(12, 'Computer Science', 'CMP-501', 5, 100, 40, 4, 'Both');

-- Class Subjects
INSERT INTO class_subjects (class_id, section_id, subject_id, teacher_id) VALUES
(1, 1, 1, 1), (1, 1, 2, 2), (1, 1, 3, 3), (1, 1, 4, 4), (1, 1, 5, 5),
(1, 2, 1, 1), (1, 2, 2, 2), (1, 2, 3, 3), (1, 2, 4, 4), (1, 2, 5, 5),
(5, 9, 9, 1), (5, 9, 10, 2), (5, 9, 11, 3), (5, 9, 12, 4);

-- Parents
INSERT INTO parents (id, user_id, father_name, mother_name, guardian_name, cnic, phone, alternate_phone, email, occupation, address, city) VALUES
(1, 7, 'Marcus Aurelius Sterling', 'Julia Sterling', 'Marcus Sterling', '42101-9988776-1', '+1 (555) 901-0001', '+1 (555) 901-0002', 'parent@edumanage.edu', 'Civil Engineer', '77 Willow Creek Road', 'Springfield'),
(2, NULL, 'Hamza Malik', 'Amina Malik', 'Hamza Malik', '42101-9988776-2', '+1 (555) 901-0003', '+1 (555) 901-0004', 'h.malik@sample.com', 'Architect', '14 Jasmine Garden', 'Springfield'),
(3, NULL, 'Richard Harrison', 'Mary Harrison', 'Richard Harrison', '42101-9988776-3', '+1 (555) 901-0005', NULL, 'r.harrison@sample.com', 'Professor', '82 Academic Blvd', 'Springfield'),
(4, NULL, 'Tariq Mehmood', 'Fatima Mehmood', 'Tariq Mehmood', '42101-9988776-4', '+1 (555) 901-0006', NULL, 't.mehmood@sample.com', 'Business Executive', '31 Orchard Road', 'Springfield'),
(5, NULL, 'James Watson', 'Clara Watson', 'James Watson', '42101-9988776-5', '+1 (555) 901-0007', NULL, 'j.watson@sample.com', 'Surgeon', '60 Hillside Ave', 'Springfield');

-- 20 Sample Students
INSERT INTO students (id, user_id, parent_id, admission_no, roll_no, first_name, last_name, gender, dob, cnic_bform, phone, email, address, city, province, admission_date, session_id, class_id, section_id, blood_group, photo, status) VALUES
(1, 8, 1, 'ADM-2025-001', '101', 'Alexander', 'Sterling', 'Male', '2018-05-14', 'B-100234-01', '+1 (555) 300-0001', 'student@edumanage.edu', '77 Willow Creek Road', 'Springfield', 'Capital', '2025-08-01', 1, 1, 1, 'O+', 'student1.png', 'active'),
(2, NULL, 1, 'ADM-2025-002', '102', 'Victoria', 'Sterling', 'Female', '2016-09-20', 'B-100234-02', '+1 (555) 300-0002', 'v.sterling@edumanage.edu', '77 Willow Creek Road', 'Springfield', 'Capital', '2025-08-01', 1, 3, 5, 'A+', 'student2.png', 'active'),
(3, NULL, 2, 'ADM-2025-003', '103', 'Bilal', 'Malik', 'Male', '2018-02-11', 'B-100234-03', '+1 (555) 300-0003', 'b.malik@sample.com', '14 Jasmine Garden', 'Springfield', 'Capital', '2025-08-02', 1, 1, 1, 'B+', 'student3.png', 'active'),
(4, NULL, 2, 'ADM-2025-004', '104', 'Zainab', 'Malik', 'Female', '2017-07-19', 'B-100234-04', '+1 (555) 300-0004', 'z.malik@sample.com', '14 Jasmine Garden', 'Springfield', 'Capital', '2025-08-02', 1, 2, 3, 'B+', 'student4.png', 'active'),
(5, NULL, 3, 'ADM-2025-005', '105', 'Lucas', 'Harrison', 'Male', '2018-11-03', 'B-100234-05', '+1 (555) 300-0005', 'l.harrison@sample.com', '82 Academic Blvd', 'Springfield', 'Capital', '2025-08-03', 1, 1, 1, 'AB+', 'student5.png', 'active'),
(6, NULL, 3, 'ADM-2025-006', '106', 'Emma', 'Harrison', 'Female', '2015-04-12', 'B-100234-06', '+1 (555) 300-0006', 'e.harrison@sample.com', '82 Academic Blvd', 'Springfield', 'Capital', '2025-08-03', 1, 4, 7, 'O+', 'student6.png', 'active'),
(7, NULL, 4, 'ADM-2025-007', '107', 'Danyal', 'Mehmood', 'Male', '2018-08-25', 'B-100234-07', '+1 (555) 300-0007', 'd.mehmood@sample.com', '31 Orchard Road', 'Springfield', 'Capital', '2025-08-04', 1, 1, 1, 'A-', 'student7.png', 'active'),
(8, NULL, 4, 'ADM-2025-008', '108', 'Mariam', 'Mehmood', 'Female', '2014-06-30', 'B-100234-08', '+1 (555) 300-0008', 'm.mehmood@sample.com', '31 Orchard Road', 'Springfield', 'Capital', '2025-08-04', 1, 5, 9, 'O+', 'student8.png', 'active'),
(9, NULL, 5, 'ADM-2025-009', '109', 'Henry', 'Watson', 'Male', '2018-01-15', 'B-100234-09', '+1 (555) 300-0009', 'h.watson@sample.com', '60 Hillside Ave', 'Springfield', 'Capital', '2025-08-05', 1, 1, 1, 'B-', 'student9.png', 'active'),
(10, NULL, 5, 'ADM-2025-010', '110', 'Grace', 'Watson', 'Female', '2016-12-04', 'B-100234-10', '+1 (555) 300-0010', 'g.watson@sample.com', '60 Hillside Ave', 'Springfield', 'Capital', '2025-08-05', 1, 3, 5, 'A+', 'student10.png', 'active'),
(11, NULL, 1, 'ADM-2025-011', '111', 'Liam', 'O’Connor', 'Male', '2018-03-29', 'B-100234-11', '+1 (555) 300-0011', 'l.oconnor@sample.com', '19 Cedar Crest', 'Springfield', 'Capital', '2025-08-06', 1, 1, 2, 'O-', 'student11.png', 'active'),
(12, NULL, 2, 'ADM-2025-012', '112', 'Sophia', 'Reyes', 'Female', '2018-10-18', 'B-100234-12', '+1 (555) 300-0012', 's.reyes@sample.com', '88 Sunset Blvd', 'Springfield', 'Capital', '2025-08-06', 1, 1, 2, 'A+', 'student12.png', 'active'),
(13, NULL, 3, 'ADM-2025-013', '113', 'Ayan', 'Qureshi', 'Male', '2017-04-09', 'B-100234-13', '+1 (555) 300-0013', 'a.qureshi@sample.com', '42 Park Lane', 'Springfield', 'Capital', '2025-08-07', 1, 2, 4, 'B+', 'student13.png', 'active'),
(14, NULL, 4, 'ADM-2025-014', '114', 'Zara', 'Ali', 'Female', '2017-09-14', 'B-100234-14', '+1 (555) 300-0014', 'z.ali@sample.com', '10 River Road', 'Springfield', 'Capital', '2025-08-07', 1, 2, 4, 'O+', 'student14.png', 'active'),
(15, NULL, 5, 'ADM-2025-015', '115', 'Noah', 'Schreiber', 'Male', '2016-02-28', 'B-100234-15', '+1 (555) 300-0015', 'n.schreiber@sample.com', '57 Forest View', 'Springfield', 'Capital', '2025-08-08', 1, 3, 6, 'AB-', 'student15.png', 'active'),
(16, NULL, 1, 'ADM-2025-016', '116', 'Chloe', 'Kim', 'Female', '2016-06-11', 'B-100234-16', '+1 (555) 300-0016', 'c.kim@sample.com', '73 Meadow Court', 'Springfield', 'Capital', '2025-08-08', 1, 3, 6, 'A-', 'student16.png', 'active'),
(17, NULL, 2, 'ADM-2025-017', '117', 'Ethan', 'Brooks', 'Male', '2015-08-07', 'B-100234-17', '+1 (555) 300-0017', 'e.brooks@sample.com', '91 Valley Road', 'Springfield', 'Capital', '2025-08-09', 1, 4, 8, 'O+', 'student17.png', 'active'),
(18, NULL, 3, 'ADM-2025-018', '118', 'Hannah', 'Miller', 'Female', '2015-11-24', 'B-100234-18', '+1 (555) 300-0018', 'h.miller@sample.com', '64 Highlands Way', 'Springfield', 'Capital', '2025-08-09', 1, 4, 8, 'B+', 'student18.png', 'active'),
(19, NULL, 4, 'ADM-2025-019', '119', 'Rayan', 'Hassan', 'Male', '2014-03-16', 'B-100234-19', '+1 (555) 300-0019', 'r.hassan@sample.com', '28 Greenfield St', 'Springfield', 'Capital', '2025-08-10', 1, 5, 10, 'A+', 'student19.png', 'active'),
(20, NULL, 5, 'ADM-2025-020', '120', 'Maya', 'Patel', 'Female', '2014-10-05', 'B-100234-20', '+1 (555) 300-0020', 'm.patel@sample.com', '15 Horizon Plaza', 'Springfield', 'Capital', '2025-08-10', 1, 5, 10, 'AB+', 'student20.png', 'active');

-- Timetables Sample
INSERT INTO timetables (session_id, class_id, section_id, day, period_number, start_time, end_time, subject_id, teacher_id, room_no) VALUES
(1, 1, 1, 'Monday', 1, '08:30:00', '09:15:00', 1, 1, 'Room 101'),
(1, 1, 1, 'Monday', 2, '09:15:00', '10:00:00', 2, 2, 'Room 101'),
(1, 1, 1, 'Monday', 3, '10:15:00', '11:00:00', 3, 3, 'Science Lab A'),
(1, 1, 1, 'Monday', 4, '11:00:00', '11:45:00', 4, 4, 'Computer Lab 1'),
(1, 1, 1, 'Monday', 5, '12:30:00', '01:15:00', 5, 5, 'Room 101'),
(1, 1, 1, 'Tuesday', 1, '08:30:00', '09:15:00', 2, 2, 'Room 101'),
(1, 1, 1, 'Tuesday', 2, '09:15:00', '10:00:00', 1, 1, 'Room 101'),
(1, 1, 1, 'Wednesday', 1, '08:30:00', '09:15:00', 3, 3, 'Room 101'),
(1, 1, 1, 'Thursday', 1, '08:30:00', '09:15:00', 4, 4, 'Computer Lab 1'),
(1, 1, 1, 'Friday', 1, '08:30:00', '09:15:00', 5, 5, 'Room 101');

-- Student Attendance Sample (Last few dates)
INSERT INTO student_attendance (session_id, student_id, class_id, section_id, attendance_date, status, remarks) VALUES
(1, 1, 1, 1, '2026-09-28', 'Present', 'On time'),
(1, 3, 1, 1, '2026-09-28', 'Present', 'On time'),
(1, 5, 1, 1, '2026-09-28', 'Late', 'Late by 15 mins'),
(1, 7, 1, 1, '2026-09-28', 'Present', 'On time'),
(1, 9, 1, 1, '2026-09-28', 'Leave', 'Medical leave'),
(1, 1, 1, 1, '2026-09-27', 'Present', 'On time'),
(1, 3, 1, 1, '2026-09-27', 'Present', 'On time'),
(1, 5, 1, 1, '2026-09-27', 'Present', 'On time'),
(1, 7, 1, 1, '2026-09-27', 'Absent', 'Uninformed'),
(1, 9, 1, 1, '2026-09-27', 'Present', 'On time');

-- Teacher Attendance
INSERT INTO teacher_attendance (teacher_id, attendance_date, status, remarks) VALUES
(1, '2026-09-28', 'Present', 'On time'),
(2, '2026-09-28', 'Present', 'On time'),
(3, '2026-09-28', 'Present', 'On time'),
(4, '2026-09-28', 'Late', 'Late 10 mins'),
(5, '2026-09-28', 'Present', 'On time');

-- Fee Types
INSERT INTO fee_types (id, name, code, description, is_recurring) VALUES
(1, 'Monthly Tuition Fee', 'TUI-MON', 'Standard monthly academic tuition charge', 1),
(2, 'Admission Registration Fee', 'ADM-FEE', 'One-time admission processing fee', 0),
(3, 'Examination Fee', 'EXAM-FEE', 'Semester examination and assessment fee', 0),
(4, 'Computer & Laboratory Fee', 'LAB-FEE', 'Access to science labs and IT equipment', 1),
(5, 'Library Fee', 'LIB-FEE', 'Annual library resource subscription', 1),
(6, 'Transport Monthly Fee', 'TRANS-FEE', 'Optional school bus commute fee', 1),
(7, 'Sports & Activity Fee', 'SPT-FEE', 'Extracurricular athletics and events', 0);

-- Fee Structures
INSERT INTO fee_structures (id, session_id, class_id, fee_type_id, amount, due_date) VALUES
(1, 1, 1, 1, 250.00, '2026-10-10'),
(2, 1, 1, 4, 35.00, '2026-10-10'),
(3, 1, 1, 5, 15.00, '2026-10-10'),
(4, 1, 2, 1, 270.00, '2026-10-10'),
(5, 1, 3, 1, 290.00, '2026-10-10'),
(6, 1, 4, 1, 310.00, '2026-10-10'),
(7, 1, 5, 1, 340.00, '2026-10-10');

-- Fee Discounts
INSERT INTO fee_discounts (id, name, discount_type, amount, description, status) VALUES
(1, 'Sibling Concession', 'Percentage', 15.00, '15% fee waiver for younger siblings', 'active'),
(2, 'Merit Scholarship', 'Percentage', 25.00, '25% waiver for top academic achievers', 'active'),
(3, 'Staff Ward Benefit', 'Percentage', 50.00, '50% discount for school employees children', 'active'),
(4, 'Hardship Need Assistance', 'Fixed', 50.00, '$50 monthly financial support deduction', 'active');

-- Student Fees Invoices
INSERT INTO student_fees (id, session_id, student_id, fee_type_id, month, amount, discount_amount, fine_amount, paid_amount, balance, due_date, status) VALUES
(1, 1, 1, 1, 'September 2026', 250.00, 0.00, 0.00, 250.00, 0.00, '2026-09-10', 'Paid'),
(2, 1, 1, 1, 'October 2026', 250.00, 0.00, 0.00, 0.00, 250.00, '2026-10-10', 'Pending'),
(3, 1, 3, 1, 'September 2026', 250.00, 37.50, 0.00, 212.50, 0.00, '2026-09-10', 'Paid'),
(4, 1, 5, 1, 'September 2026', 250.00, 0.00, 20.00, 100.00, 170.00, '2026-09-10', 'Partial'),
(5, 1, 7, 1, 'September 2026', 250.00, 0.00, 25.00, 0.00, 275.00, '2026-09-10', 'Overdue'),
(6, 1, 8, 1, 'September 2026', 340.00, 0.00, 0.00, 340.00, 0.00, '2026-09-10', 'Paid');

-- Fee Payments
INSERT INTO fee_payments (id, receipt_no, session_id, student_id, payment_date, total_amount, discount_amount, fine_amount, paid_amount, balance_remaining, payment_method, transaction_ref, remarks, received_by) VALUES
(1, 'REC-2026-0001', 1, 1, '2026-09-08', 250.00, 0.00, 0.00, 250.00, 0.00, 'Online', 'TXN-984310', 'Full September tuition payment received via card', 3),
(2, 'REC-2026-0002', 1, 3, '2026-09-09', 250.00, 37.50, 0.00, 212.50, 0.00, 'Bank', 'BNK-771239', 'Sibling concession applied, bank deposit', 3),
(3, 'REC-2026-0003', 1, 5, '2026-09-15', 250.00, 0.00, 20.00, 100.00, 170.00, 'Cash', NULL, 'Partial installment received at accounts counter', 3),
(4, 'REC-2026-0004', 1, 8, '2026-09-05', 340.00, 0.00, 0.00, 340.00, 0.00, 'Cheque', 'CHQ-55018', 'Grade 5 tuition paid via check', 3);

INSERT INTO fee_payment_items (payment_id, student_fee_id, amount_applied) VALUES
(1, 1, 250.00),
(2, 3, 212.50),
(3, 4, 100.00),
(4, 6, 340.00);

-- Expenses
INSERT INTO expense_categories (id, name, description) VALUES
(1, 'Electricity & Utilities', 'Power grid, backup generators, and water bills'),
(2, 'Faculty Salaries', 'Staff payroll and teaching honorariums'),
(3, 'Stationery & Printing', 'Exam papers, report cards, and office stationery'),
(4, 'Campus Maintenance', 'Janitorial, repairs, and landscaping'),
(5, 'Laboratory & IT Consumables', 'Software licenses, toner, and hardware');

INSERT INTO expenses (id, category_id, title, description, amount, expense_date, payment_method, reference_no, created_by) VALUES
(1, 1, 'Campus Electric Bill - Sept', 'Monthly main campus electricity consumption', 1420.00, '2026-09-20', 'Bank', 'ELEC-9021', 3),
(2, 3, 'Examination Answer Sheets Printing', '5,000 booklets for upcoming Mid-Terms', 680.00, '2026-09-18', 'Cash', 'PRN-4410', 3),
(3, 4, 'Air Conditioning Servicing', 'Routine HVAC filters and maintenance in blocks A & B', 450.00, '2026-09-22', 'Cash', 'HVAC-102', 3),
(4, 5, 'Fiber Internet & Wi-Fi Lease', 'High speed leased line quarterly charge', 380.00, '2026-09-25', 'Online', 'ISP-8831', 3);

-- Exam Module
INSERT INTO exam_types (id, name, description) VALUES
(1, 'Mid-Term Examinations', 'Comprehensive first semester assessment'),
(2, 'Final Term Examinations', 'End of academic year cumulative testing'),
(3, 'Monthly Progress Test', 'Monthly modular quizzes and formative tests');

INSERT INTO exams (id, session_id, exam_type_id, title, start_date, end_date, status) VALUES
(1, 1, 1, 'Fall Semester Mid-Terms 2026', '2026-10-15', '2026-10-25', 'Active'),
(2, 1, 2, 'Annual Final Examinations 2026', '2026-05-10', '2026-05-22', 'Upcoming');

INSERT INTO exam_subjects (id, exam_id, class_id, subject_id, exam_date, start_time, end_time, max_marks, pass_marks, room_no) VALUES
(1, 1, 1, 1, '2026-10-15', '09:00:00', '11:00:00', 100, 40, 'Hall A'),
(2, 1, 1, 2, '2026-10-17', '09:00:00', '11:00:00', 100, 40, 'Hall A'),
(3, 1, 1, 3, '2026-10-19', '09:00:00', '11:00:00', 100, 40, 'Hall A'),
(4, 1, 1, 4, '2026-10-21', '09:00:00', '11:00:00', 100, 40, 'Computer Lab 1'),
(5, 1, 5, 9, '2026-10-15', '09:00:00', '11:30:00', 100, 40, 'Hall B'),
(6, 1, 5, 10, '2026-10-17', '09:00:00', '11:30:00', 100, 40, 'Hall B'),
(7, 1, 5, 11, '2026-10-19', '09:00:00', '11:30:00', 100, 40, 'Hall B');

INSERT INTO grades (id, grade_name, min_percentage, max_percentage, grade_point, remarks) VALUES
(1, 'A+', 90.00, 100.00, 4.00, 'Outstanding Academic Achievement'),
(2, 'A', 80.00, 89.99, 3.70, 'Excellent Mastery'),
(3, 'B', 70.00, 79.99, 3.00, 'Good Understanding'),
(4, 'C', 60.00, 69.99, 2.00, 'Satisfactory Progress'),
(5, 'D', 50.00, 59.99, 1.00, 'Pass / Needs Improvement'),
(6, 'F', 0.00, 49.99, 0.00, 'Fail / Intensive Support Required');

-- Marks Seed
INSERT INTO marks (exam_id, exam_subject_id, student_id, marks_obtained, max_marks, percentage, grade, is_passed, remarks, created_by) VALUES
(1, 1, 1, 94.50, 100.00, 94.50, 'A+', 1, 'Superb problem-solving speed', 4),
(1, 2, 1, 88.00, 100.00, 88.00, 'A', 1, 'Rich vocabulary and neat writing', 4),
(1, 3, 1, 92.00, 100.00, 92.00, 'A+', 1, 'Thorough concept retention', 4),
(1, 1, 3, 78.00, 100.00, 78.00, 'B', 1, 'Good effort, check mental calculations', 4),
(1, 2, 3, 82.50, 100.00, 82.50, 'A', 1, 'Fluent expressions and answers', 4),
(1, 1, 5, 86.00, 100.00, 86.00, 'A', 1, 'Strong conceptual grasp', 4),
(1, 2, 5, 74.00, 100.00, 74.00, 'B', 1, 'Improve essay structure', 4),
(1, 5, 8, 91.00, 100.00, 91.00, 'A+', 1, 'Class topper in Mathematics', 4),
(1, 6, 8, 89.00, 100.00, 89.00, 'A', 1, 'Exemplary essay work', 4),
(1, 7, 8, 95.00, 100.00, 95.00, 'A+', 1, 'Outstanding practical and theory', 4);

-- Library
INSERT INTO book_categories (id, name) VALUES
(1, 'General Science & Astronomy'),
(2, 'Mathematics & Logic'),
(3, 'World Literature & Poetry'),
(4, 'History & Biographies'),
(5, 'Information Technology');

INSERT INTO authors (id, name) VALUES
(1, 'Carl Sagan'),
(2, 'Martin Gardner'),
(3, 'C.S. Lewis'),
(4, 'J.K. Rowling'),
(5, 'Donald Knuth');

INSERT INTO books (id, isbn, title, author_id, publisher, category_id, edition, quantity, available_quantity, price, shelf_no) VALUES
(1, '978-0345539434', 'Cosmos: A Personal Voyage', 1, 'Random House', 1, '1st Illustrated', 8, 7, 24.99, 'Shelf-SC-01'),
(2, '978-0486432694', 'Entertaining Mathematical Puzzles', 2, 'Dover', 2, '2nd Edition', 10, 9, 14.50, 'Shelf-MTH-03'),
(3, '978-0064404990', 'The Chronicles of Narnia', 3, 'HarperCollins', 3, 'Collector Ed.', 12, 11, 29.99, 'Shelf-LIT-05'),
(4, '978-0439708180', 'Harry Potter & The Sorcerer Stone', 4, 'Scholastic', 3, '20th Anniv.', 15, 14, 18.00, 'Shelf-LIT-02'),
(5, '978-0201896831', 'The Art of Computer Programming', 5, 'Addison-Wesley', 5, '3rd Edition', 4, 4, 65.00, 'Shelf-CS-04');

INSERT INTO book_issues (id, book_id, student_id, issue_date, due_date, return_date, fine_amount, book_condition, status, issued_by) VALUES
(1, 1, 1, '2026-09-14', '2026-09-28', NULL, 0.00, 'Good', 'Issued', 6),
(2, 3, 2, '2026-09-10', '2026-09-24', '2026-09-24', 0.00, 'Good', 'Returned', 6),
(3, 4, 3, '2026-09-01', '2026-09-15', NULL, 6.50, 'Minor Wear', 'Overdue', 6);

-- Transport
INSERT INTO drivers (id, name, cnic, phone, license_no, license_expiry, status) VALUES
(1, 'Rashid Mahmood', '42101-5544332-1', '+1 (555) 700-1001', 'DL-HTV-98214', '2028-11-30', 'active'),
(2, 'George Peterson', '42101-5544332-2', '+1 (555) 700-1002', 'DL-HTV-88410', '2027-04-15', 'active');

INSERT INTO vehicles (id, vehicle_no, registration_no, vehicle_type, capacity, driver_id, status) VALUES
(1, 'BUS-01', 'SP-REG-801', 'Coaster Bus', 32, 1, 'active'),
(2, 'BUS-02', 'SP-REG-802', 'Mini Van', 16, 2, 'active');

INSERT INTO transport_routes (id, route_name, pickup_point, drop_point, monthly_fee, vehicle_id) VALUES
(1, 'Route 1: Westside Express', 'Greenfield Heights -> Sunset Plaza', 'Springfield Academy Main Gate', 60.00, 1),
(2, 'Route 2: North Hills Shuttle', 'Pine Ridge -> Highland Towers', 'Springfield Academy Main Gate', 55.00, 2);

INSERT INTO student_transport (student_id, route_id, session_id, start_date, status) VALUES
(1, 1, 1, '2025-08-15', 'active'),
(3, 1, 1, '2025-08-15', 'active'),
(5, 2, 1, '2025-08-15', 'active');

-- Inventory
INSERT INTO inventory_categories (id, name, description) VALUES
(1, 'Classroom Furniture', 'Desks, teacher tables, ergonomic chairs'),
(2, 'Laboratory Supplies', 'Beakers, test tubes, chemical reagents'),
(3, 'IT & Audio-Visual', 'Projectors, mouse, keyboards, HDMI cables'),
(4, 'Sports Gear', 'Footballs, basketballs, cricket sets, cones');

INSERT INTO suppliers (id, company_name, contact_person, phone, email, address) VALUES
(1, 'Apex Educational Supplies Co.', 'Trevor Vance', '+1 (555) 880-9901', 'sales@apex-supplies.com', '12 Industrial Estate, Sector 4'),
(2, 'SmartTech Hardware Solutions', 'Karen Liu', '+1 (555) 880-9902', 'support@smarttech-hw.com', '405 Tech Boulevard, Silicon Row');

INSERT INTO inventory_items (id, category_id, supplier_id, name, code, unit, min_stock, current_stock, purchase_price, location) VALUES
(1, 1, 1, 'Dual Student Desk & Chairs', 'FUR-DSK-01', 'Set', 10, 65, 85.00, 'Main Warehouse - Bay 1'),
(2, 2, 1, 'Borosilicate 250ml Beaker', 'LAB-BKR-250', 'Pieces', 25, 120, 4.50, 'Science Prep Lab'),
(3, 3, 2, 'Wireless Optical Mouse', 'IT-ACC-MS01', 'Units', 8, 32, 12.00, 'IT Store Room B'),
(4, 3, 2, 'Full HD Multimedia Projector', 'IT-PRJ-HD', 'Units', 3, 8, 420.00, 'AV Equipment Locker'),
(5, 4, 1, 'Official Size 5 Soccer Ball', 'SPT-BAL-05', 'Pieces', 6, 22, 19.50, 'Gymnasium Storage');

-- Employees & HR
INSERT INTO employees (id, user_id, employee_code, name, cnic, phone, email, address, department_id, designation_id, joining_date, basic_salary, status) VALUES
(1, 4, 'EMP-101', 'Mrs. Elizabeth Bennett', '42101-1234567-1', '+1 (555) 101-2001', 'teacher@edumanage.edu', '12 Rosewood Lane', 1, 1, '2019-08-01', 3500.00, 'active'),
(2, 3, 'EMP-102', 'David Sterling', '42101-7654321-1', '+1 (555) 202-3001', 'accountant@edumanage.edu', '17 Finance Way', 3, 5, '2018-03-01', 3800.00, 'active'),
(3, 5, 'EMP-103', 'Clara Oswald', '42101-7654321-2', '+1 (555) 202-3002', 'receptionist@edumanage.edu', '55 Maple Avenue', 2, 4, '2022-02-15', 2600.00, 'active'),
(4, 6, 'EMP-104', 'Arthur Pendelton', '42101-7654321-3', '+1 (555) 202-3003', 'librarian@edumanage.edu', '101 Library Court', 4, 6, '2016-09-01', 2900.00, 'active');

INSERT INTO leave_types (id, name, days_allowed, description) VALUES
(1, 'Casual Leave', 10, 'Short personal emergency or unplanned leave'),
(2, 'Sick Leave', 12, 'Certified medical recuperation leave'),
(3, 'Annual Vacation Leave', 20, 'Scheduled holiday breaks'),
(4, 'Maternity / Paternity', 60, 'Parental childcare support leave');

INSERT INTO salary_structures (employee_id, basic_salary, medical_allowance, house_rent, transport_allowance, tax_deduction, other_deductions, net_salary) VALUES
(1, 3500.00, 300.00, 500.00, 200.00, 200.00, 0.00, 4300.00),
(2, 3800.00, 350.00, 550.00, 200.00, 250.00, 0.00, 4650.00),
(3, 2600.00, 200.00, 350.00, 150.00, 100.00, 0.00, 3200.00),
(4, 2900.00, 250.00, 400.00, 150.00, 120.00, 0.00, 3580.00);

INSERT INTO payroll (id, employee_id, month_year, basic_salary, allowances, bonus, overtime, deductions, net_salary, payment_date, payment_method, status, payslip_no, generated_by) VALUES
(1, 1, 'August 2026', 3500.00, 1000.00, 150.00, 0.00, 200.00, 4450.00, '2026-08-31', 'Bank Transfer', 'Paid', 'SLIP-202608-01', 3),
(2, 2, 'August 2026', 3800.00, 1100.00, 0.00, 0.00, 250.00, 4650.00, '2026-08-31', 'Bank Transfer', 'Paid', 'SLIP-202608-02', 3),
(3, 3, 'August 2026', 2600.00, 700.00, 0.00, 0.00, 100.00, 3200.00, '2026-08-31', 'Bank Transfer', 'Paid', 'SLIP-202608-03', 3);

-- Assignments
INSERT INTO assignments (id, session_id, class_id, section_id, subject_id, teacher_id, title, description, issue_date, due_date, attachment) VALUES
(1, 1, 1, 1, 1, 1, 'Mental Math Worksheet: Addition & Subtraction Patterns', 'Complete exercises 1 to 20 on page 42 of workbook. Show step calculations.', '2026-09-25', '2026-10-02', 'homework-math-101.pdf'),
(2, 1, 1, 1, 2, 2, 'English Reading Journal & Short Story Summary', 'Read Chapter 3 of Stuart Little and write 5 complete sentences about Stuart journey.', '2026-09-26', '2026-10-03', NULL),
(3, 1, 5, 9, 12, 4, 'Introduction to HTML5 Tags & Web Page Layout', 'Build a simple 1-page personal profile containing head, body, headings, and an ordered list.', '2026-09-24', '2026-10-01', 'cs-lab-guide-01.pdf');

-- Notices
INSERT INTO notices (id, title, description, date, audience, status, created_by) VALUES
(1, 'Annual Sports Day 2026 Scheduled for November 12', 'All students and faculty are invited to register for track, relay, and field events with their respective physical education teachers before October 15th.', '2026-09-26', 'Everyone', 'Active', 1),
(2, 'Parent-Teacher Meeting (PTM) for Mid-Term Preview', 'Dear Parents, PTM will be held on Saturday, October 10th from 9:00 AM to 1:00 PM to review quarter attendance and test readiness.', '2026-09-24', 'Parents', 'Active', 2),
(3, 'Faculty Academic Workshop on Modern Pedagogy', 'Mandatory teaching methodology seminar in Seminar Hall 2 on Friday at 3:30 PM.', '2026-09-22', 'Teachers', 'Active', 1);

-- Messages
INSERT INTO messages (sender_id, receiver_id, subject, body, is_read) VALUES
(1, 4, 'Curriculum milestone review for Grade 1 Mathematics', 'Hello Elizabeth, please prepare the progress report for Grade 1 Math by this Wednesday. Thank you!', 1),
(4, 1, 'Re: Curriculum milestone review for Grade 1 Mathematics', 'Dear Dr. Sarah, all chapters up to lesson 6 are thoroughly completed. Report is being compiled.', 1),
(7, 3, 'Inquiry regarding October Tuition Slip online payment', 'Dear Accounts office, can we make payment using the online gateway portal? Thank you, Marcus.', 0);

-- Notifications
INSERT INTO notifications (user_id, title, message, link, is_read, type) VALUES
(1, 'New Student Enrolled', 'Student Alexander Sterling has been successfully enrolled into Grade 1 - Section A.', 'students/view.php?id=1', 1, 'success'),
(1, 'Fee Payment Received', 'Receipt #REC-2026-0001 for $250.00 was recorded by David Sterling.', 'fees/receipt.php?id=1', 1, 'info'),
(4, 'Marks Entry Deadline', 'Mid-Term marks submission window will close on Oct 25.', 'examinations/marks.php', 0, 'warning'),
(7, 'Fee Due Reminder', 'October 2026 tuition fee invoice is due on October 10.', 'fees/index.php', 0, 'warning');

-- Audit Logs
INSERT INTO audit_logs (user_id, action, module, record_id, details, ip_address) VALUES
(1, 'LOGIN', 'Authentication', 1, 'Super Admin logged into administration console', '127.0.0.1'),
(1, 'UPDATE_SETTINGS', 'Settings', NULL, 'Updated school motto and active academic session', '127.0.0.1'),
(3, 'FEE_COLLECTION', 'Fees', 1, 'Collected tuition payment $250.00 for student ID 1 (REC-2026-0001)', '127.0.0.1'),
(4, 'MARKS_SAVED', 'Examinations', 1, 'Entered Mid-Term marks for Grade 1 Mathematics', '127.0.0.1');
