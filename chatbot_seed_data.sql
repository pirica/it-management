INSERT INTO `it_settings` (`company_id`, `contact_email`, `contact_phone`, `hours_of_operation`, `escalation_procedure`)
VALUES (1, 'support@techcorp.com', '+1-555-0123', 'Mon-Fri 8:00 AM - 6:00 PM', '1. Contact Helpdesk\n2. If unresolved in 4h, contact IT Manager\n3. High priority issues: call +1-555-0911');

INSERT INTO `knowledge_base` (`company_id`, `category`, `title`, `content`) VALUES
(1, 'Technical Documentation', 'VPN Setup Guide', 'To set up the VPN, please follow these steps:\n1. Open Cisco AnyConnect.\n2. Enter vpn.techcorp.com.\n3. Login with your company credentials.'),
(1, 'Common Issues', 'Password Reset Procedure', 'If you forgot your password, you can reset it via the portal at https://reset.techcorp.com or contact the IT team.'),
(1, 'IT Policies', 'Acceptable Use Policy', 'Employees must use company hardware for business purposes only. Unauthorized software installation is prohibited.'),
(1, 'Network & Connectivity', 'WiFi Access', 'Connect to "TechCorp_Guest" for visitors. Employees should use "TechCorp_Secure" with their domain login.'),
(1, 'Software Compatibility', 'Supported Browsers', 'We officially support Google Chrome, Microsoft Edge, and Mozilla Firefox. Internet Explorer is no longer supported.');
