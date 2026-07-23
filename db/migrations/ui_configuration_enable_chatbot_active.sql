-- DML only: backfill ui_configuration.enable_chatbot to match schema default (1).
-- Why: demo seeds and older saves stored 0; chatbot should be active for all roles by default.
UPDATE `ui_configuration`
SET `enable_chatbot` = 1
WHERE `enable_chatbot` = 0;
