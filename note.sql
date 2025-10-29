-- notes_app.sql
-- Create database
CREATE DATABASE IF NOT EXISTS notes_app;
USE notes_app;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Notes table
CREATE TABLE IF NOT EXISTS notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    category ENUM('general', 'work', 'personal', 'ideas', 'todo') DEFAULT 'general',
    is_favorite BOOLEAN DEFAULT FALSE,
    is_pinned BOOLEAN DEFAULT FALSE,
    color VARCHAR(7) DEFAULT '#ffffff',
    tags JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_category (category),
    INDEX idx_created_at (created_at),
    INDEX idx_updated_at (updated_at)
);

-- Tags table for advanced tagging system
CREATE TABLE IF NOT EXISTS tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    color VARCHAR(7) DEFAULT '#6b7280',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_tag (user_id, name)
);

-- Note tags relationship table (many-to-many)
CREATE TABLE IF NOT EXISTS note_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    note_id INT NOT NULL,
    tag_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
    UNIQUE KEY unique_note_tag (note_id, tag_id)
);

-- Notebooks/Categories table
CREATE TABLE IF NOT EXISTS notebooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#3b82f6',
    icon VARCHAR(50) DEFAULT '📒',
    is_default BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
);

-- Add notebook_id to notes table
ALTER TABLE notes ADD COLUMN notebook_id INT NULL AFTER user_id;
ALTER TABLE notes ADD FOREIGN KEY (notebook_id) REFERENCES notebooks(id) ON DELETE SET NULL;

-- Templates table for note templates
CREATE TABLE IF NOT EXISTS templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    content TEXT NOT NULL,
    category VARCHAR(50) DEFAULT 'general',
    is_public BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Shared notes table for collaboration
CREATE TABLE IF NOT EXISTS shared_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    note_id INT NOT NULL,
    shared_by INT NOT NULL,
    shared_with INT NOT NULL,
    permission ENUM('view', 'edit') DEFAULT 'view',
    share_token VARCHAR(100) UNIQUE,
    expires_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_with) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_share_token (share_token)
);

-- Activity log table
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    note_id INT NULL,
    action VARCHAR(50) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
);

-- Insert default user (username: admin, password: admin123)
INSERT INTO users (username, email, password) VALUES 
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Insert default notebooks
INSERT INTO notebooks (user_id, name, description, color, icon, is_default) VALUES 
(1, 'General', 'Default notebook for all notes', '#3b82f6', '📒', TRUE),
(1, 'Work', 'Work-related notes and tasks', '#ef4444', '💼', FALSE),
(1, 'Personal', 'Personal notes and thoughts', '#10b981', '🌟', FALSE),
(1, 'Ideas', 'Creative ideas and brainstorming', '#8b5cf6', '💡', FALSE),
(1, 'To-Do', 'Tasks and reminders', '#f59e0b', '✅', FALSE);

-- Insert some sample notes
INSERT INTO notes (user_id, notebook_id, title, content, category, is_favorite, is_pinned) VALUES 
(1, 1, 'Welcome to NotionNotes!', '## Welcome to Your New Notes App!\n\nThis is a sample note to get you started. You can:\n- Create new notes\n- Organize them by categories\n- Search through your content\n- Mark favorites\n- And much more!\n\n### Getting Started\n1. Create your first note\n2. Organize them using categories\n3. Use search to find notes quickly\n4. Customize your workspace', 'general', TRUE, TRUE),

(1, 2, 'Project Planning Meeting', '### Meeting Notes - Project Phoenix\n**Date:** 2024-01-15\n**Attendees:** John, Sarah, Mike\n\n#### Agenda:\n1. Project timeline review\n2. Resource allocation\n3. Risk assessment\n\n#### Action Items:\n- [ ] Finalize project scope\n- [ ] Assign team members\n- [ ] Set up project management tools\n- [ ] Schedule client meeting', 'work', FALSE, TRUE),

(1, 3, 'Personal Goals 2024', '## Personal Goals for 2024\n\n### Health & Fitness\n- [ ] Exercise 3 times per week\n- [ ] Learn healthy cooking recipes\n- [ ] Meditate daily\n\n### Learning\n- [ ] Complete PHP advanced course\n- [ ] Learn React framework\n- [ ] Read 12 books\n\n### Travel\n- [ ] Visit Japan in spring\n- [ ] Weekend hiking trips\n- [ ] Explore local museums', 'personal', TRUE, FALSE),

(1, 4, 'App Feature Ideas', '## Potential App Features\n\n### Core Features\n- Real-time collaboration\n- File attachments\n- Version history\n- Templates library\n\n### Advanced Features\n- AI-powered suggestions\n- Cross-platform sync\n- Advanced formatting options\n- Integration with other tools\n\n### UI/UX Improvements\n- Dark mode toggle\n- Custom themes\n- Keyboard shortcuts\n- Mobile app', 'ideas', FALSE, FALSE),

(1, 5, 'Weekly Tasks', '## This Week''s Tasks\n\n### Monday\n- [ ] Team meeting 10 AM\n- [ ] Finish project proposal\n- [ ] Gym session 6 PM\n\n### Tuesday\n- [ ] Client call 2 PM\n- [ ] Review code changes\n- [ ] Grocery shopping\n\n### Wednesday\n- [ ] Dentist appointment 11 AM\n- [ ] Write blog post\n- [ ] Plan weekend activities', 'todo', FALSE, TRUE);

-- Insert some sample tags
INSERT INTO tags (user_id, name, color) VALUES 
(1, 'important', '#ef4444'),
(1, 'meeting', '#3b82f6'),
(1, 'ideas', '#8b5cf6'),
(1, 'personal', '#10b981'),
(1, 'work', '#f59e0b'),
(1, 'urgent', '#dc2626');

-- Associate tags with notes
INSERT INTO note_tags (note_id, tag_id) VALUES 
(1, 1), -- Welcome note -> important
(2, 2), -- Project meeting -> meeting
(2, 5), -- Project meeting -> work
(2, 6), -- Project meeting -> urgent
(3, 1), -- Personal goals -> important
(3, 4), -- Personal goals -> personal
(4, 3), -- App ideas -> ideas
(5, 5), -- Weekly tasks -> work
(5, 6); -- Weekly tasks -> urgent

-- Insert sample templates
INSERT INTO templates (user_id, name, content, category, is_public) VALUES 
(1, 'Meeting Notes', '## Meeting Notes\n\n**Date:** \n**Attendees:** \n\n### Agenda:\n1. \n2. \n3. \n\n### Discussion:\n- \n- \n- \n\n### Action Items:\n- [ ] \n- [ ] \n- [ ] \n\n### Next Meeting:', 'work', TRUE),

(1, 'Project Plan', '# Project Plan: [Project Name]\n\n## Overview\n**Objective:** \n**Timeline:** \n**Budget:** \n\n## Team Members\n- \n- \n- \n\n## Milestones\n- [ ] \n- [ ] \n- [ ] \n\n## Risks & Challenges\n- \n- \n- ', 'work', TRUE),

(1, 'Personal Journal', '# Journal Entry - [Date]\n\n### Today''s Highlights\n- \n- \n- \n\n### What I Learned\n- \n- \n- \n\n### Goals for Tomorrow\n- [ ] \n- [ ] \n- [ ] \n\n### Reflection\n', 'personal', TRUE),

(1, 'Weekly Review', '# Weekly Review - [Week of]\n\n## Accomplishments\n- \n- \n- \n\n## Challenges Faced\n- \n- \n- \n\n## Lessons Learned\n- \n- \n- \n\n## Goals for Next Week\n- [ ] \n- [ ] \n- [ ] ', 'personal', TRUE);

-- Insert sample activity log
INSERT INTO activity_log (user_id, note_id, action, description, ip_address) VALUES 
(1, 1, 'create', 'Created note: Welcome to NotionNotes!', '192.168.1.100'),
(1, 2, 'create', 'Created note: Project Planning Meeting', '192.168.1.100'),
(1, 3, 'create', 'Created note: Personal Goals 2024', '192.168.1.100'),
(1, 1, 'update', 'Updated note content', '192.168.1.100'),
(1, 2, 'favorite', 'Marked note as favorite', '192.168.1.100'),
(1, 3, 'pin', 'Pinned note to top', '192.168.1.100');

-- Create views for easier querying
CREATE VIEW note_details AS
SELECT 
    n.*,
    u.username,
    u.email,
    nb.name as notebook_name,
    nb.color as notebook_color,
    GROUP_CONCAT(DISTINCT t.name) as tag_names,
    GROUP_CONCAT(DISTINCT t.color) as tag_colors
FROM notes n
LEFT JOIN users u ON n.user_id = u.id
LEFT JOIN notebooks nb ON n.notebook_id = nb.id
LEFT JOIN note_tags nt ON n.id = nt.note_id
LEFT JOIN tags t ON nt.tag_id = t.id
WHERE n.deleted_at IS NULL
GROUP BY n.id;

-- Create indexes for better performance
CREATE INDEX idx_notes_user_category ON notes(user_id, category);
CREATE INDEX idx_notes_favorite ON notes(user_id, is_favorite);
CREATE INDEX idx_notes_pinned ON notes(user_id, is_pinned);
CREATE INDEX idx_notes_search ON notes(user_id, title, content(255));
CREATE INDEX idx_tags_user ON tags(user_id);
CREATE INDEX idx_activity_user ON activity_log(user_id, created_at);

-- Create stored procedure for note search
DELIMITER //
CREATE PROCEDURE search_user_notes(
    IN p_user_id INT,
    IN p_search_term VARCHAR(255)
)
BEGIN
    SELECT n.*, 
           MATCH(n.title, n.content) AGAINST (p_search_term IN NATURAL LANGUAGE MODE) as relevance
    FROM notes n
    WHERE n.user_id = p_user_id 
    AND n.deleted_at IS NULL
    AND (MATCH(n.title, n.content) AGAINST (p_search_term IN NATURAL LANGUAGE MODE)
         OR n.title LIKE CONCAT('%', p_search_term, '%')
         OR n.content LIKE CONCAT('%', p_search_term, '%'))
    ORDER BY relevance DESC, n.updated_at DESC;
END //
DELIMITER ;

-- Enable full-text search on notes
ALTER TABLE notes ADD FULLTEXT(title, content);

-- Create trigger for updated_at
DELIMITER //
CREATE TRIGGER update_note_timestamp 
BEFORE UPDATE ON notes 
FOR EACH ROW 
SET NEW.updated_at = CURRENT_TIMESTAMP;
//
DELIMITER ;

-- Create trigger for activity logging
DELIMITER //
CREATE TRIGGER log_note_creation 
AFTER INSERT ON notes 
FOR EACH ROW 
BEGIN
    INSERT INTO activity_log (user_id, note_id, action, description)
    VALUES (NEW.user_id, NEW.id, 'create', CONCAT('Created note: ', NEW.title));
END //
DELIMITER ;

-- Show database summary
SELECT 
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM notes WHERE deleted_at IS NULL) as total_notes,
    (SELECT COUNT(*) FROM tags) as total_tags,
    (SELECT COUNT(*) FROM notebooks) as total_notebooks,
    (SELECT COUNT(*) FROM templates) as total_templates;
