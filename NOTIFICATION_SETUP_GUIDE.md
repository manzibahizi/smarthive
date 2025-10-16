# Smart Hive Solution - Notification System Setup Guide

## 🚨 **Enhanced Alert System with Email & SMS**

The Smart Hive Solution now includes a comprehensive notification system that sends alerts via email and SMS when critical hive conditions are detected.

## 📧 **Email Notifications**

### **Features:**
- **Professional HTML emails** with Smart Hive branding
- **Detailed alert information** including hive ID, values, and thresholds
- **Priority-based formatting** (critical alerts get high priority)
- **Automatic sending** when sensor thresholds are exceeded

### **Setup:**
1. **Configure SMTP settings** in your `.env` file:
```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password
SMTP_FROM_EMAIL=alerts@smarthivesolution.com
SMTP_FROM_NAME="Smart Hive Solution"
```

2. **For Gmail users:**
   - Enable 2-factor authentication
   - Generate an "App Password" for the application
   - Use the app password in `SMTP_PASSWORD`

## 📱 **SMS Notifications**

### **Features:**
- **Critical alerts only** to avoid spam
- **Concise messages** with essential information
- **Twilio integration** for reliable delivery
- **International phone number support**

### **Setup:**
1. **Sign up for Twilio** (https://www.twilio.com)
2. **Get your credentials:**
   - Account SID
   - Auth Token
   - Phone number for sending SMS
3. **Configure in `.env` file:**
```env
SMS_API_KEY=your-account-sid:your-auth-token
SMS_FROM_NUMBER=+1234567890
```

## 🔧 **User Management**

### **Enhanced User Profiles:**
- **Email field** (required for new users)
- **Phone field** (optional)
- **Validation** for email and phone formats
- **Admin interface** updated with new fields

### **Admin Features:**
- **Create users** with email and phone
- **Edit user contact information**
- **Test notification system**
- **View notification status**

## ⚙️ **Notification Settings Page**

### **Access:**
- Navigate to **Notifications** in the sidebar
- Or visit `/notification-settings.html`

### **Features:**
- **Email configuration** with enable/disable toggle
- **SMS configuration** with enable/disable toggle
- **Alert type preferences** (temperature, humidity, gas, etc.)
- **Test notifications** to verify setup
- **Real-time validation** of email and phone formats

## 🚨 **Alert Types & Thresholds**

### **Temperature Alerts:**
- **Critical Low:** < 10°C (SMS + Email)
- **Warning Low:** < 15°C (Email only)
- **Warning High:** > 35°C (Email only)
- **Critical High:** > 40°C (SMS + Email)

### **Humidity Alerts:**
- **Critical Low:** < 20% (SMS + Email)
- **Warning Low:** < 30% (Email only)
- **Warning High:** > 80% (Email only)
- **Critical High:** > 90% (SMS + Email)

### **Gas Level Alerts:**
- **Warning High:** > 100 ppm (Email only)
- **Critical High:** > 200 ppm (SMS + Email)

### **Battery Alerts:**
- **Warning Low:** < 20% (Email only)
- **Critical Low:** < 10% (SMS + Email)

## 🔄 **How It Works**

### **Automatic Process:**
1. **Sensor data** is received via API
2. **Thresholds are checked** against current values
3. **Alerts are created** in the database
4. **Notifications are sent** to all active users
5. **Results are logged** for monitoring

### **Notification Priority:**
- **High Priority (SMS + Email):** Critical alerts
- **Normal Priority (Email only):** Warning alerts
- **Browser notifications:** All alerts (if enabled)

## 🧪 **Testing the System**

### **Admin Test Function:**
1. Go to **Admin Panel** → **Test Notifications**
2. Enter test email and/or phone number
3. Click **Send Test**
4. Check your email and phone for test messages

### **User Test Function:**
1. Go to **Notification Settings**
2. Configure your email and phone
3. Click **Test Notifications**
4. Verify you receive test messages

## 📊 **Monitoring & Logs**

### **Log Locations:**
- **Email logs:** Check PHP error logs
- **SMS logs:** Check PHP error logs
- **Alert logs:** Database `alerts` table
- **Notification logs:** Error log entries

### **Log Messages:**
```
Email alert sent to user 1 for temperature alert in hive H001
SMS alert sent to user 1 for temperature alert in hive H001
Failed to send alert notifications: [error details]
```

## 🛠️ **Troubleshooting**

### **Email Issues:**
- **Check SMTP credentials** in `.env` file
- **Verify Gmail app password** is correct
- **Check firewall** allows SMTP connections
- **Test with different email providers**

### **SMS Issues:**
- **Verify Twilio credentials** are correct
- **Check phone number format** (+1234567890)
- **Ensure Twilio account** has sufficient credits
- **Test with different phone numbers**

### **General Issues:**
- **Check PHP error logs** for detailed errors
- **Verify database connection** is working
- **Ensure users have email/phone** configured
- **Test with admin account** first

## 🔒 **Security Considerations**

### **Email Security:**
- **Use app passwords** instead of main passwords
- **Enable 2FA** on email accounts
- **Use dedicated email** for alerts
- **Monitor for suspicious activity**

### **SMS Security:**
- **Protect Twilio credentials**
- **Use environment variables** for sensitive data
- **Monitor SMS usage** and costs
- **Implement rate limiting** if needed

## 📈 **Performance Optimization**

### **Email Optimization:**
- **Batch notifications** for multiple alerts
- **Use HTML templates** for better formatting
- **Implement retry logic** for failed sends
- **Monitor delivery rates**

### **SMS Optimization:**
- **Send only critical alerts** via SMS
- **Use concise messages** to save costs
- **Implement delivery tracking**
- **Monitor Twilio usage**

## 🎯 **Best Practices**

### **For Administrators:**
1. **Test thoroughly** before going live
2. **Monitor notification delivery** regularly
3. **Keep credentials secure** and updated
4. **Have backup notification methods**

### **For Users:**
1. **Keep contact information** up to date
2. **Test notifications** after setup
3. **Configure preferences** appropriately
4. **Report issues** promptly

## 🚀 **Future Enhancements**

### **Planned Features:**
- **Push notifications** for mobile apps
- **Webhook integrations** for external systems
- **Custom alert rules** per user
- **Notification scheduling** and quiet hours
- **Multi-language support** for alerts
- **Rich media attachments** in emails

---

## 📞 **Support**

If you encounter any issues with the notification system:

1. **Check this guide** for common solutions
2. **Review error logs** for detailed information
3. **Test with admin account** to isolate issues
4. **Contact support** with specific error messages

The Smart Hive Solution notification system is designed to keep you informed about your hives 24/7, ensuring the health and safety of your bee colonies!
