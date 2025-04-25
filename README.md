# Student Management System

A web-based student management system with features for both students and teachers.

## Project Structure

```
project/
├── api/                    # API endpoints
│   ├── assignments/        # Assignment-related endpoints
│   ├── auth/              # Authentication endpoints
│   ├── extensions/        # Extension request endpoints
│   ├── feedback/          # Feedback-related endpoints
│   ├── progress/          # Progress tracking endpoints
│   └── submissions/       # Submission-related endpoints
├── config/                # Configuration files
│   └── database.php       # Database configuration
├── includes/              # Common PHP includes
│   ├── functions.php      # Utility functions
│   └── init.php          # Initialization file
├── logs/                  # Log files
├── public/               # Publicly accessible files
│   ├── assets/          # Static assets
│   ├── css/            # Stylesheet files
│   ├── js/             # JavaScript files
│   ├── images/         # Image files and media
│   │   ├── *.png      # UI images
│   │   ├── *.jpg      # Background images
│   │   └── *.mov      # Video files
│   ├── uploads/        # User uploads
│   └── *.html         # HTML pages
└── .htaccess            # Apache configuration
```

## Setup Instructions

1. Configure your web server to point to the `public` directory
2. Import the database schema from `database/schema.sql`
3. Update database credentials in `config/database.php`
4. Ensure the following directories are writable:
   - `logs/`
   - `public/uploads/`

## File Organization

### Frontend Assets
- All static assets (images, CSS, JavaScript) are in the `public` directory
- Images are stored in `public/images/`
- JavaScript files are in `public/js/`
- CSS files are in `public/css/`

### Backend Files
- API endpoints are organized by feature in the `api` directory
- Common PHP functions are in `includes/functions.php`
- Database configuration is in `config/database.php`

## API Endpoints

### Assignments
- `POST /api/assignments/create` - Create new assignment
- `GET /api/assignments/list` - List assignments

### Submissions
- `POST /api/submissions/submit` - Submit assignment
- `GET /api/submissions/list` - List submissions

### Feedback
- `POST /api/feedback/provide` - Provide feedback
- `GET /api/feedback/view` - View feedback

### Progress Tracking
- `GET /api/progress/view` - View student progress
  - Parameters: `student_id`
  - Returns: Assignment completion stats and detailed progress

### Extension Requests
- `POST /api/extensions/request` - Request assignment extension
  - Parameters: `assignment_id`, `student_id`, `reason`, `requested_date`
- `GET /api/extensions/manage` - List extension requests (for teachers)
  - Parameters: `teacher_id`
- `POST /api/extensions/manage` - Approve/reject extension requests
  - Parameters: `request_id`, `status`, `teacher_id`

## Security Features

- Input sanitization
- Prepared statements for SQL queries
- File upload validation
- CORS protection
- Directory access restrictions
- Error logging

## Frontend Pages

### Student Interface
- `StudentDashboard.html` - Main student dashboard
- `StudentSignup.html` - Student registration
- `student-feedback.html` - View feedback
- `view-progress.html` - View progress
- `submission-overview.html` - View submissions

### Teacher Interface
- `teacher-dashboard.html` - Main teacher dashboard
- `teacher.html` - Teacher profile
- `assign-work.html` - Create assignments
- `extension-requests.html` - Manage extension requests

### Common Pages
- `index.html` - Landing page
- `role-selection.html` - Choose role (student/teacher) 