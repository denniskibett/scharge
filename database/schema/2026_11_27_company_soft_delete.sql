-- Soft delete for companies: a company removed from the CRM keeps its row (and history) but is hidden everywhere
-- (App\Models\Company global scope "not_deleted"). Imports still match it, so it is never added again.
ALTER TABLE companies ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL, ADD INDEX companies_deleted_at_index (deleted_at);
