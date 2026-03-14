# 🥗 FitTrack AI

تطبيق ويب لتتبع السعرات الحرارية ومؤشر كتلة الجسم مع الذكاء الاصطناعي.

## المميزات

- ✅ تسجيل دخول / إنشاء حساب
- 📊 حساب BMI ومعدل الأيض (BMR/TDEE)
- 🎯 تحديد هدف (إنقاص / زيادة / ثبات وزن)
- 🤖 خطة غذائية ورياضية بالذكاء الاصطناعي (Gemini AI مجاناً)
- 📷 تصوير الأكل وتحديد السعرات بالـ AI
- 📝 تتبع يومي للسعرات والماكرو (بروتين/كارب/دهون)
- ✏️ تعديل وحذف سجلات الطعام
- 📈 رسم بياني أسبوعي
- 👨‍💼 لوحة تحكم للأدمن

## المتطلبات

- PHP 8.0+
- SQLite (مدمج مع PHP)
- cURL extension
- Web server (Apache/Nginx) أو `php -S localhost:8000`

## التثبيت

1. ضع الملفات على السيرفر
2. تأكد أن مجلد `database/` و `assets/uploads/` قابل للكتابة
3. افتح `setup.php` في المتصفح للتأكد
4. سجّل دخول بـ: `admin@fittrack.com` / `admin123`

## تفعيل الذكاء الاصطناعي (مجاني)

1. احصل على مفتاح مجاني من: https://aistudio.google.com/app/apikey
2. في `config/config.php` غيّر:
```php
define('GEMINI_API_KEY', 'YOUR_KEY_HERE');
```
أو ضع متغير بيئة:
```bash
export GEMINI_API_KEY=your_key_here
```

## تشغيل محلي

```bash
php -S localhost:8000
# ثم افتح: http://localhost:8000
```

## البنية

```
calories/
├── index.php          # صفحة الدخول
├── dashboard.php      # الرئيسية
├── bmi.php            # حساب BMI
├── goals.php          # تحديد الهدف
├── tracker.php        # تتبع السعرات
├── admin/index.php    # لوحة الأدمن
├── api/               # APIs
│   ├── auth.php
│   ├── bmi.php
│   ├── goals.php
│   ├── food.php
│   ├── ai.php
│   └── admin.php
├── config/            # الإعدادات
│   ├── config.php
│   └── database.php
├── assets/
│   ├── css/style.css
│   ├── js/app.js
│   └── uploads/       # صور الأكل
└── database/          # SQLite DB (يُنشأ تلقائياً)
```
