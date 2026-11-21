
-- ============================================
-- VulnShop Notice Board Schema
-- ============================================
-- Purpose: 공지사항 테이블 생성
-- Database: vulnshop
-- Dependency: users 테이블이 먼저 존재해야 함
-- ============================================

CREATE TABLE IF NOT EXISTS notices (

    id INT NOT NULL AUTO_INCREMENT,

    title VARCHAR(255) NOT NULL,

    content TEXT NOT NULL,

    author_id INT NOT NULL,

    is_pinned BOOLEAN NOT NULL DEFAULT FALSE,

    created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    INDEX idx_notices_order (
        is_pinned,
        created_at
    ),

    CONSTRAINT fk_notices_author
        FOREIGN KEY (author_id)
        REFERENCES users(id)
        ON DELETE RESTRICT

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_0900_ai_ci;
