# Synapse AI Notes (PHP Version)

## Project Description

Synapse AI Notes is a web-based note-taking application developed as a student project. It aims to provide an intelligent platform for users to create, manage, and organize their notes, enhanced with AI-powered features for summarization and categorization. This version is implemented using PHP for the backend, MySQL for the database, and HTML, CSS (Bootstrap/Tailwind), and JavaScript for the frontend. User authentication is handled via Google OAuth2.

The core idea is to explore the practical integration of AI (specifically Hugging Face Inference APIs) into everyday productivity tools, making note management more efficient and insightful.

## Key Features

  * **User Authentication:** Secure login via Google OAuth2.
  * **Note Management (CRUD):** Create, Read, Update, and Delete notes.
  * **Subject Management (CRUD):** Organize notes under different subjects/categories created by the user.
  * **AI-Powered Summarization:** Automatic generation of concise summaries for longer notes using Hugging Face API.
  * **AI-Powered Categorization:** Suggestion of relevant categories for notes based on their content using Hugging Face API.
  * **Dynamic Dashboard:** View all notes, filter by subject, and search by note title.
  * **User Profile Management:** View user profile details (name, email, profile picture).
  * **Responsive Design:** User interface adaptable to different screen sizes.

## Technologies Used

  * **Backend:** PHP
  * **Database:** MySQL (managed via phpMyAdmin)
  * **Frontend:** HTML, CSS (Bootstrap for modals and some layout, Tailwind CSS for primary styling), JavaScript
  * **Authentication:** Google OAuth2 (via `google/apiclient` PHP library)
  * **AI Services:** Hugging Face Inference API (for Text Classification and Summarization)
  * **Dependency Management (PHP):** Composer
  * **Local Development Server:** XAMPP / WAMP / MAMP (or similar)

## Setup and Installation

1.  **Clone the Repository (or download files):**

    ```bash
    git clone [https://github.com/YOUR_USERNAME/YOUR_REPOSITORY_NAME.git](https://github.com/YOUR_USERNAME/YOUR_REPOSITORY_NAME.git)
    cd YOUR_REPOSITORY_NAME
    ```

2.  **Web Server:**

      * Ensure you have a local web server environment like XAMPP, WAMP, or MAMP installed and running (with Apache and MySQL services started).
      * Place the project folder inside your web server's document root (e.g., `htdocs` for XAMPP).

3.  **Database Setup:**

      * Using phpMyAdmin (or any MySQL client), create a new database (e.g., `synapse_ai_notes_db`).
      * Import the provided SQL schema (from the `database_schema.sql` file provided earlier, or execute the SQL commands directly) to create the necessary tables (`app_users`, `subjects`, `notes`).

4.  **Configuration (`config.php`):**

      * In the root directory of the project, create a file named `config.php`.
      * **Important:** This file should NOT be committed to version control (ensure it's in your `.gitignore` file).
      * Copy the following structure into `config.php` and fill in your actual credentials:
        ```php
        <?php
        // Database Configuration
        define('DB_HOST', 'localhost');
        define('DB_USERNAME', 'YOUR_DB_USERNAME'); // e.g., 'root'
        define('DB_PASSWORD', 'YOUR_DB_PASSWORD'); // e.g., '' if no password for root
        define('DB_NAME', 'synapse_ai_notes_db'); // Your database name
        define('DB_CHARSET', 'utf8mb4');

        // Google OAuth Credentials
        define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID_HERE');
        define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET_HERE');
        // Ensure this matches exactly what's in your Google Cloud Console
        define('GOOGLE_REDIRECT_URI', 'http://localhost/YOUR_PROJECT_FOLDER_NAME/google-callback.php'); 

        // Hugging Face API Token
        define('HF_API_TOKEN', 'YOUR_HUGGINGFACE_API_TOKEN_HERE');
        define('HF_API_URL_CLASSIFICATION', '[https://api-inference.huggingface.co/models/MoritzLaurer/DeBERTa-v3-base-mnli-fever-anli](https://api-inference.huggingface.co/models/MoritzLaurer/DeBERTa-v3-base-mnli-fever-anli)'); // Or your chosen model
        define('HF_API_URL_SUMMARIZATION', '[https://api-inference.huggingface.co/models/sshleifer/distilbart-cnn-12-6](https://api-inference.huggingface.co/models/sshleifer/distilbart-cnn-12-6)'); // Or your chosen model

        // Error Reporting (for development)
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        ?>
        ```
      * Replace `YOUR_DB_USERNAME`, `YOUR_DB_PASSWORD`, `YOUR_GOOGLE_CLIENT_ID_HERE`, `YOUR_GOOGLE_CLIENT_SECRET_HERE`, `YOUR_PROJECT_FOLDER_NAME`, and `YOUR_HUGGINGFACE_API_TOKEN_HERE` with your actual values.
      * Ensure the `GOOGLE_REDIRECT_URI` matches the one configured in your Google Cloud Console project.

5.  **Install Composer Dependencies:**

      * If you haven't already, install Composer ([getcomposer.org](https://getcomposer.org/)).
      * Open a terminal/command prompt in the project's root directory.
      * Run the command:
        ```bash
        composer install
        ```
        (This will install the `google/apiclient` library into a `vendor` folder).

## How to Run

1.  Ensure your Apache and MySQL services (from XAMPP/WAMP/MAMP) are running.
2.  Open your web browser and navigate to the project's URL (e.g., `http://localhost/YOUR_PROJECT_FOLDER_NAME/` or `http://localhost/YOUR_PROJECT_FOLDER_NAME/index.php`).
3.  You should see the login page. Sign in with Google to access the application.

## Project Status

This is a student project currently under development. The PHP version aims to implement the core functionalities. Future work may involve refining features, enhancing UI/UX, and potentially exploring other technology stacks (like Java Spring Boot for a comparative implementation).

-----

<p align="center">
  Made with ❤️ by h7
</p>
