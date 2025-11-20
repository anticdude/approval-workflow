CREATE DATABASE IF NOT EXISTS approval_workflow;
USE approval_workflow;

CREATE TABLE IF NOT EXISTS requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jira_number VARCHAR(50) NOT NULL,
    status ENUM('NEW', 'APP_OWNER_APPROVED', 'SECURITY_APPROVED', 'SRE_APPROVED', 'READY_TO_DEPLOY') DEFAULT 'NEW',
    app_owner_approved_at DATETIME NULL,
    security_approved_at DATETIME NULL,
    sre_approved_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);