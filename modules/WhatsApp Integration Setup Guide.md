# WhatsApp Integration Setup Guide
## Deliverance Church Management System

---

## 🎉 WhatsApp Integration Complete!

Your Church Management System now has full WhatsApp integration with the following features:

### ✨ Features Included:

1. **Send to Individual Members**
   - Select members from your database
   - Add custom phone numbers
   - Personalized messages with placeholders

2. **Send to WhatsApp Groups**
   - Manage multiple WhatsApp groups
   - Send to entire groups with one click
   - Track group communications

3. **Custom Recipients**
   - Add any phone number (even non-members)
   - Comma-separated list of numbers
   - Supports Kenyan and international formats

4. **Message Features**
   - Real-time message preview
   - Character count tracking
   - Emoji support
   - Personalization placeholders: {first_name}, {last_name}, {church_name}

5. **Complete History**
   - Track all sent messages
   - Delivery status monitoring
   - Resend failed messages
   - Search and filter capabilities

---

## 📱 Your WhatsApp Business Number

**Phone Number:** 0745 600 377  
**International Format:** +254 745 600 377

---

## 🚀 Setup Instructions

### Option 1: Development Mode (Simulation)

The system is currently configured for **development/testing mode** with message simulation.

**What this means:**
- All messages are simulated (not actually sent)
- You can test all features without API costs
- 98% success rate simulation
- Perfect for testing the system

**To use in development mode:**
1. No additional setup needed!
2. Just start sending test messages
3. Check the history to see simulated results

### Option 2: Production Mode (Real WhatsApp API)

To send real WhatsApp messages, you need to set up **WhatsApp Business API**.

#### Step 1: Get WhatsApp Business API Access

1. **Via Facebook Business (Recommended)**
   - Go to [Facebook Business](https://business.facebook.com/)
   - Create a Business Account (if you don't have one)
   - Set up WhatsApp Business API
   - Get your credentials

2. **Via Cloud API Providers** (Easier Option)
   - **Twilio**: https://www.twilio.com/whatsapp
   - **360Dialog**: https://www.360dialog.com/
   - **MessageBird**: https://messagebird.com/
   - **Infobip**: https://www.infobip.com/

#### Step 2: Get Your API Credentials

You'll need:
- **Access Token** (API Key)
- **Phone Number ID**
- **Business Account ID**
- **Webhook Verify Token**

#### Step 3: Configure in System

1. Go to **Administration > System Settings**
2. Find WhatsApp settings section
3. Enter your credentials:
   ```
   - WhatsApp Access Token: [Your token]
   - Phone Number ID: [Your ID]
   - Business Account ID: [Your account ID]
   ```

4. Or update database directly:
   ```sql
   UPDATE system_settings 
   SET setting_value = 'your_access_token_here' 
   WHERE setting_key = 'whatsapp_access_token';
   
   UPDATE system_settings 
   SET setting_value = 'your_phone_number_id' 
   WHERE setting_key = 'whatsapp_phone_number_id';
   ```

#### Step 4: Verify Phone Number

1. Your WhatsApp Business number (0745600377) must be verified
2. Follow WhatsApp's verification process
3. Ensure the number is active and can receive/send messages

---

## 📋 Database Setup

Run this SQL to create WhatsApp tables:

```sql
-- Run the database_whatsapp_tables.sql file
-- It creates:
-- - whatsapp_groups
-- - whatsapp_history
-- - whatsapp_individual
-- - whatsapp_templates
-- - whatsapp_webhooks
```

---

## 🎯 How to Use

### Send Individual Messages

1. Go to **Communication Center > Send WhatsApp**
2. Select members from your database OR add custom numbers
3. Compose your message
4. Use placeholders for personalization
5. Preview before sending
6. Click "Send WhatsApp Messages"

### Send to Groups

1. Go to **Communication Center > Send WhatsApp**
2. Check "Send to WhatsApp Group"
3. Select a group (or add new group)
4. Compose message
5. Send to entire group

### Add WhatsApp Groups

1. Click "Add New Group" button
2. Enter group name and description
3. Optional: Add WhatsApp Group ID
4. Save and use for future messages

### View History

1. Go to **Communication Center > WhatsApp History**
2. View all sent messages
3. Check delivery status
4. Resend failed messages
5. Filter by date, status, etc.

---

## 💡 Best Practices

### Message Guidelines:

1. **Keep it Professional**
   - Use proper greeting
   - Include church name
   - Sign off appropriately

2. **Personalize Messages**
   ```
   Good morning {first_name},
   
   This is a reminder about our Sunday service at 9:00 AM.
   
   God bless you!
   - {church_name}
   ```

3. **Timing Matters**
   - Send during appropriate hours (8 AM - 8 PM)
   - Avoid late night messages
   - Consider time zones if applicable

4. **Respect Privacy**
   - Only send to consented members
   - Provide opt-out options
   - Don't spam

### Phone Number Formats:

**Accepted Formats:**
- Kenyan: `0745600377`, `0712345678`, `0798765432`
- International: `+254745600377`, `254745600377`
- System auto-formats to: `254745600377` (WhatsApp format)

**Multiple Numbers:**
```
0745600377, 0712345678, +254798765432
```

---

## 🔧 Troubleshooting

### Messages Not Sending?

**Check:**
1. ✅ API credentials are correct
2. ✅ Phone number is verified
3. ✅ Access token hasn't expired
4. ✅ Phone numbers are in correct format
5. ✅ Check error logs: `logs/error.log`

### In Development Mode?

**Confirm by:**
- Check if messages show as "simulated" in logs
- No actual WhatsApp notifications received
- All messages succeed (98% rate)

**To switch to Production:**
- Add valid API credentials in system settings
- System auto-detects and uses real API

### Common Errors:

**Error: "Invalid phone number"**
- Solution: Use format `0745600377` or `+254745600377`

**Error: "API credentials missing"**
- Solution: Add credentials in system settings or runs in simulation

**Error: "Message failed to send"**
- Solution: Check recipient number is active on WhatsApp

---

## 📊 Message Tracking

### Delivery Status:

- **Pending**: Queued for sending
- **Sent**: Successfully sent to WhatsApp
- **Delivered**: Delivered to recipient's phone
- **Read**: Recipient has read the message
- **Failed**: Failed to send (with error details)

### Success Metrics:

View comprehensive statistics:
- Total messages sent
- Success/failure rates
- Monthly trends
- Individual message status

---

## 💰 Cost Considerations

### WhatsApp Business API Pricing:

**Typical costs (varies by provider):**
- Business Initiated Messages: $0.005 - $0.02 per message
- User Initiated Messages: Free (24-hour window)
- Template Messages: Lower cost
- Session Messages: Higher cost

**Recommendations:**
1. Use approved templates for lower costs
2. Respond within 24-hour window when possible
3. Batch non-urgent messages
4. Monitor usage and set budgets

---

## 🔐 Security & Privacy

### Data Protection:

1. **Encryption**: All API communications use HTTPS
2. **Access Control**: Role-based permissions
3. **Audit Logs**: Complete activity tracking
4. **Data Privacy**: GDPR/data protection compliance

### Member Consent:

**Important:** Ensure you have consent to send WhatsApp messages
- Document consent in member records
- Provide opt-out mechanisms
- Honor unsubscribe requests promptly

---

## 🎨 Advanced Features

### Message Templates (Future):

Create pre-approved templates for:
- Event reminders
- Birthday wishes
- Service announcements
- Follow-up messages
- Giving reminders

### Media Messages (Coming Soon):

Send:
- Images (flyers, announcements)
- Videos (sermons, testimonies)
- Documents (bulletins, PDFs)
- Audio (worship songs)

### Interactive Messages (Future):

- Quick reply buttons
- Call-to-action buttons
- List messages
- Location sharing

---

## 🆘 Support & Resources

### Documentation:

- **WhatsApp Business API Docs**: https://developers.facebook.com/docs/whatsapp
- **Cloud API Guide**: https://developers.facebook.com/docs/whatsapp/cloud-api
- **PHP SDK**: https://github.com/netflie/whatsapp-cloud-api

### Need Help?

1. **Check Logs**: `logs/error.log` and `logs/app.log`
2. **Test Mode**: Use test send to verify setup
3. **System Status**: Check communication dashboard
4. **Database**: Verify `whatsapp_history` table has records

### Common Questions:

**Q: Can I use my personal WhatsApp number?**
A: No, you need WhatsApp Business API (different from regular WhatsApp)

**Q: Can I send to international numbers?**
A: Yes! Use international format: +[country code][number]

**Q: How many messages can I send at once?**
A: Unlimited, but consider rate limits from your provider

**Q: Can recipients reply?**
A: Yes! Replies create 24-hour messaging window (webhook feature coming)

**Q: Is it free?**
A: Development mode is free. Production requires WhatsApp Business API (paid)

---

## 🚦 Getting Started Checklist

### Immediate Use (Development):

- [x] WhatsApp integration installed
- [x] Database tables created
- [x] Send test messages (simulated)
- [x] View message history
- [x] Add WhatsApp groups
- [x] Select members and send

### Production Setup:

- [ ] Sign up for WhatsApp Business API
- [ ] Verify business phone number (0745600377)
- [ ] Get API credentials
- [ ] Update system settings with credentials
- [ ] Send real test message
- [ ] Verify delivery
- [ ] Train staff on usage

---

## 📁 File Structure

```
church-cms/
├── includes/
│   └── WhatsAppSender.php          # WhatsApp sending engine
├── modules/
│   └── sms/
│       ├── send_whatsapp.php       # Send WhatsApp page
│       ├── whatsapp_history.php    # Message history
│       └── ajax/
│           ├── send_test_whatsapp.php
│           ├── save_whatsapp_group.php
│           └── resend_whatsapp.php
└── database_whatsapp_tables.sql    # Database schema
```

---

## 🎊 Success!

Your WhatsApp integration is complete and ready to use!

### Next Steps:

1. **Test in Development Mode**
   - Send test messages to yourself
   - Try different recipient types
   - Add WhatsApp groups
   - Check history and statistics

2. **Plan for Production**
   - Research WhatsApp Business API providers
   - Compare pricing and features
   - Prepare business verification documents
   - Set up API account

3. **Train Your Team**
   - Show them how to send messages
   - Explain best practices
   - Demonstrate history tracking
   - Set usage guidelines

4. **Start Communicating!**
   - Send service reminders
   - Birthday wishes
   - Event announcements
   - Prayer requests
   - Community updates

---

## 📞 Your WhatsApp Business Setup

**Business Phone**: 0745 600 377  
**Format for API**: 254745600377  
**Country Code**: Kenya (+254)  

**Status**: ✅ Ready for Development Mode  
**Production**: Pending API credentials

---

## 🙏 Ministry Impact

WhatsApp integration helps your church:

✨ **Better Communication**
- Instant reach to members
- Higher engagement rates
- Personal touch with names

📱 **Modern Approach**
- Members' preferred platform
- Real-time notifications
- Group ministry coordination

📊 **Track Everything**
- Delivery confirmation
- Read receipts
- Engagement metrics

💰 **Cost Effective**
- Lower than SMS costs
- Free incoming messages
- Efficient bulk sending

🌍 **Global Reach**
- International members
- Missionaries abroad
- Multi-location churches

---

## 🎯 Quick Reference

### Send Message Flow:
1. Communication Center → Send WhatsApp
2. Select recipients (members/custom/group)
3. Compose message with placeholders
4. Preview → Send

### Check Status:
1. Communication Center → WhatsApp History
2. View all messages and status
3. Resend failed if needed

### Test Sending:
1. Draft your message
2. Click "Send Test to My Number"
3. Check 0745600377 for test message

---

**Remember**: Currently in development/simulation mode. Add API credentials for production use!

For questions or assistance, contact your system administrator.

**God bless your ministry! 🙏**