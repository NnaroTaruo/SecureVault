-- Existing installations only: read transactional-vault-tables.md first.
-- Pause online writes and take a verified structure-and-data backup before running.
-- ALTER TABLE commits implicitly; these four changes are not one transaction.
-- Preserve all columns, indexes, character sets, salts, hashes, and ciphertexts.
-- Re-running is safe for data, but rebuilds even tables already using InnoDB.

ALTER TABLE users ENGINE=InnoDB;
ALTER TABLE categories ENGINE=InnoDB;
ALTER TABLE accounts ENGINE=InnoDB;
ALTER TABLE sessions ENGINE=InnoDB;
