# Deploy — SQL + Admin

1. **`database/install.sql`** import गर्नुहोस्।
2. **`includes/database.local.php`** मा DB credentials।
3. **Superadmin** — `includes/superadmin-config.local.php` (`.example` बाट copy) मा username + `SUPER_ADMIN_INITIAL_PASSWORD`। cPanel बाट edit गर्न मिल्छ; **git मा commit नगर्नुहोस्**।
4. Admin पासवर्ड: **public reset URL छैन**। Superadmin/Admin ले **Admin व्यवस्थापन** (`manage-admins.php`) बाट अरूको पासवर्ड सेट गर्छन्; त्यसपछि त्यो user ले **पासवर्ड परिवर्तन** मा आफ्नो नयाँ पासवर्ड राख्छ। आफ्नो पासवर्ड सबैले **Profile → पासवर्ड परिवर्तन** बाट।
5. **`git pull` अघि:** यदि `includes/.cred-master.key` छ भने **backup** गर्नुहोस् (यो फाइल Git मा छैन; pull पछि पनि local मा राख्नुपर्छ)। हराएमा नयाँ key बन्छ र **Credentials vault** मा पहिले सेभ गरेका encrypted पासवर्ड decrypt हुँदैनन्।
