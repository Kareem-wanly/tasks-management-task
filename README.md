Team Task Management System

A Full-Stack Team Task Management System built with Laravel (Backend REST API) and React Vite 



 Tech Stack

 Backend: Laravel 12
 Frontend: React, Vite, React Plain CSS
 Database: MySQL
 API: RESTful API
 Authentication: Laravel Sanctum



 Project Structure

team-task-manager
 backend/    Laravel REST API Application
 frontend/   React Single Page Application (SPA)


 -Local Setup Instructions

 PHP >= 8.2

 Composer

 Node.js >= 18.x & npm

 MySQL

 The API server will run at: http://127.0.0.1:8000

 
 -PHP settings

 Task files go up to 20 MB (Bitrix24 step 4.5). PHP must accept that much in
 one request, or uploads fail with 413 before the app sees them. In php.ini
 (or the server's PHP settings):

     upload_max_filesize = 25M
     post_max_size = 26M

 The file is streamed to Bitrix24 from disk, so memory_limit does not need to
 grow with the file size.
