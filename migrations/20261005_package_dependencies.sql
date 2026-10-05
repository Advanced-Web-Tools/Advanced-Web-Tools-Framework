-- Apply once to existing MySQL databases before installing packages with dependencies.
ALTER TABLE awt_package ADD COLUMN dependencies TEXT DEFAULT NULL;
UPDATE awt_package SET dependencies = '[]' WHERE dependencies IS NULL;
