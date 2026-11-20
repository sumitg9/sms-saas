<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

class NotificationTemplateSeeder extends Seeder
{
    /**
     * Seed core editable notification templates for all 11 required event types across channels.
     */
    public function run(): void
    {
        $templates = [
            // 1. Student Absence
            [
                'event_name' => 'student_absence',
                'channel' => 'sms',
                'language' => 'en',
                'subject' => null,
                'body_template' => 'Dear Parent, your child {{student_name}} of {{class_name}} is marked absent today ({{date}}) at {{school_name}}. Please contact school if this is unnotified.',
                'placeholders' => ['student_name', 'class_name', 'date', 'school_name'],
            ],
            [
                'event_name' => 'student_absence',
                'channel' => 'whatsapp',
                'language' => 'en',
                'subject' => null,
                'body_template' => '🚨 *Daily Attendance Alert*\n\nDear Parent,\nYour ward *{{student_name}}* (Class: {{class_name}}) has been recorded *ABSENT* today, {{date}}.\n\nIf you have already submitted a leave application, please disregard. For inquiries, reply to this message.\n\n_{{school_name}}_',
                'placeholders' => ['student_name', 'class_name', 'date', 'school_name'],
            ],
            [
                'event_name' => 'student_absence',
                'channel' => 'email',
                'language' => 'en',
                'subject' => 'Student Absence Notification - {{student_name}} ({{date}})',
                'body_template' => "Dear Parent/Guardian,\n\nThis is to notify you that your child, {{student_name}}, enrolled in {{class_name}}, was marked absent for the morning attendance call on {{date}} at {{school_name}}.\n\nIf your child is unwell, please submit an online leave application via the Parent Portal.\n\nWarm regards,\nAttendance Office\n{{school_name}}",
                'placeholders' => ['student_name', 'class_name', 'date', 'school_name'],
            ],
            [
                'event_name' => 'student_absence',
                'channel' => 'in_app',
                'language' => 'en',
                'subject' => 'Absence Recorded',
                'body_template' => '{{student_name}} was marked absent on {{date}}.',
                'placeholders' => ['student_name', 'date'],
            ],

            // 2. Fee Reminder
            [
                'event_name' => 'fee_reminder',
                'channel' => 'sms',
                'language' => 'en',
                'subject' => null,
                'body_template' => 'Dear Parent, fee balance of Rs. {{amount}} for {{student_name}} is due by {{due_date}}. Pay online via {{payment_url}} to avoid late fine. - {{school_name}}',
                'placeholders' => ['student_name', 'amount', 'due_date', 'payment_url', 'school_name'],
            ],
            [
                'event_name' => 'fee_reminder',
                'channel' => 'whatsapp',
                'language' => 'en',
                'subject' => null,
                'body_template' => "💳 *School Fee Due Notice*\n\nDear Parent,\nThis is a friendly reminder that tuition fee invoice for *{{student_name}}* of amount *Rs. {{amount}}* is due by *{{due_date}}*.\n\n👉 Pay safely online: {{payment_url}}\n\nThank you,\nFinance Department, {{school_name}}",
                'placeholders' => ['student_name', 'amount', 'due_date', 'payment_url', 'school_name'],
            ],
            [
                'event_name' => 'fee_reminder',
                'channel' => 'email',
                'language' => 'en',
                'subject' => 'Fee Payment Reminder for {{student_name}} - Due {{due_date}}',
                'body_template' => "Dear Parent,\n\nPlease be reminded that the quarterly school fees of Rs. {{amount}} for {{student_name}} ({{class_name}}) are due on {{due_date}}.\n\nYou may view invoice breakdown and pay securely online through the Parent Portal: {{payment_url}}.\n\nThank you for your prompt cooperation.\n\nAccounts Office\n{{school_name}}",
                'placeholders' => ['student_name', 'class_name', 'amount', 'due_date', 'payment_url', 'school_name'],
            ],

            // 3. Payment Confirmation
            [
                'event_name' => 'payment_confirmation',
                'channel' => 'sms',
                'language' => 'en',
                'subject' => null,
                'body_template' => 'Payment received! Rs. {{amount}} paid successfully for {{student_name}}. Receipt #{{receipt_number}}. Thank you, {{school_name}}.',
                'placeholders' => ['amount', 'student_name', 'receipt_number', 'school_name'],
            ],
            [
                'event_name' => 'payment_confirmation',
                'channel' => 'whatsapp',
                'language' => 'en',
                'subject' => null,
                'body_template' => "✅ *Payment Successful*\n\nWe have received payment of *Rs. {{amount}}* for *{{student_name}}*.\n\nReceipt Number: *{{receipt_number}}*\nTransaction Date: {{date}}\n\nOfficial receipt is available for download in your Parent Portal.\n\n{{school_name}}",
                'placeholders' => ['amount', 'student_name', 'receipt_number', 'date', 'school_name'],
            ],
            [
                'event_name' => 'payment_confirmation',
                'channel' => 'email',
                'language' => 'en',
                'subject' => 'Payment Receipt {{receipt_number}} - {{school_name}}',
                'body_template' => "Dear Parent,\n\nThank you for your payment. We confirm receipt of Rs. {{amount}} on {{date}} for {{student_name}}.\n\nReceipt Number: {{receipt_number}}\nPayment Mode: {{payment_method}}\nBalance Outstanding: Rs. {{balance_due}}\n\nYour downloadable digital receipt is attached to your account portal.\n\nAccounts Office\n{{school_name}}",
                'placeholders' => ['amount', 'date', 'student_name', 'receipt_number', 'payment_method', 'balance_due', 'school_name'],
            ],

            // 4. Examination Schedules
            [
                'event_name' => 'exam_schedule',
                'channel' => 'whatsapp',
                'language' => 'en',
                'subject' => null,
                'body_template' => "📅 *Examination Timetable Published*\n\nThe schedule for *{{exam_name}}* for {{class_name}} is now published.\nExaminations commence from *{{start_date}}*.\n\nCheck room assignments and timings in the Student/Parent Portal: {{portal_url}}\n\n{{school_name}}",
                'placeholders' => ['exam_name', 'class_name', 'start_date', 'portal_url', 'school_name'],
            ],
            [
                'event_name' => 'exam_schedule',
                'channel' => 'email',
                'language' => 'en',
                'subject' => 'Examination Schedule: {{exam_name}} - {{class_name}}',
                'body_template' => "Dear Student and Parents,\n\nThe complete datesheet and examination regulations for {{exam_name}} have been released for {{class_name}}.\n\nExaminations start: {{start_date}}\nReporting Time: {{reporting_time}}\n\nPlease review syllabus guidelines and admit card instructions in the portal: {{portal_url}}.\n\nAcademic Examination Board\n{{school_name}}",
                'placeholders' => ['exam_name', 'class_name', 'start_date', 'reporting_time', 'portal_url', 'school_name'],
            ],

            // 5. Examination Results
            [
                'event_name' => 'exam_result',
                'channel' => 'sms',
                'language' => 'en',
                'subject' => null,
                'body_template' => 'Results out! {{exam_name}} results for {{student_name}} have been published with GPA {{gpa}}. View full report card at {{portal_url}}. - {{school_name}}',
                'placeholders' => ['exam_name', 'student_name', 'gpa', 'portal_url', 'school_name'],
            ],
            [
                'event_name' => 'exam_result',
                'channel' => 'whatsapp',
                'language' => 'en',
                'subject' => null,
                'body_template' => "🏆 *Report Card Published*\n\nResults for *{{exam_name}}* have been declared for *{{student_name}}*.\n\nOverall Score: *{{percentage}}%* (GPA: *{{gpa}}*)\nResult: *{{result_status}}*\n\nDownload full transcript: {{portal_url}}\n\n{{school_name}}",
                'placeholders' => ['exam_name', 'student_name', 'percentage', 'gpa', 'result_status', 'portal_url', 'school_name'],
            ],

            // 6. Homework Assignments
            [
                'event_name' => 'homework_assignment',
                'channel' => 'in_app',
                'language' => 'en',
                'subject' => 'New Homework: {{subject_name}}',
                'body_template' => 'New assignment "{{homework_title}}" due on {{due_date}} ({{max_marks}} marks).',
                'placeholders' => ['subject_name', 'homework_title', 'due_date', 'max_marks'],
            ],
            [
                'event_name' => 'homework_assignment',
                'channel' => 'push',
                'language' => 'en',
                'subject' => 'New Homework Assigned',
                'body_template' => '{{subject_name}}: {{homework_title}} is due by {{due_date}}.',
                'placeholders' => ['subject_name', 'homework_title', 'due_date'],
            ],

            // 7. School Announcements
            [
                'event_name' => 'school_announcement',
                'channel' => 'email',
                'language' => 'en',
                'subject' => 'Important Circular: {{title}} - {{school_name}}',
                'body_template' => "Dear Parents, Students, and Staff,\n\nPlease read the following official school circular:\n\n{{content}}\n\nFor details, visit the portal: {{portal_url}}\n\nAdministration\n{{school_name}}",
                'placeholders' => ['title', 'content', 'portal_url', 'school_name'],
            ],
            [
                'event_name' => 'school_announcement',
                'channel' => 'in_app',
                'language' => 'en',
                'subject' => 'School Notice: {{title}}',
                'body_template' => '{{content}}',
                'placeholders' => ['title', 'content'],
            ],

            // 8. Holiday Notifications
            [
                'event_name' => 'holiday_notification',
                'channel' => 'sms',
                'language' => 'en',
                'subject' => null,
                'body_template' => 'School will remain closed on {{date}} on account of {{holiday_name}}. Regular classes resume on {{resume_date}}. - {{school_name}}',
                'placeholders' => ['date', 'holiday_name', 'resume_date', 'school_name'],
            ],
            [
                'event_name' => 'holiday_notification',
                'channel' => 'whatsapp',
                'language' => 'en',
                'subject' => null,
                'body_template' => "🎉 *Holiday Notice*\n\nDear Parents & Students,\nThe school will observe a holiday on *{{date}}* on occasion of *{{holiday_name}}*.\n\nClasses will resume as per regular timetable on *{{resume_date}}*.\n\n{{school_name}}",
                'placeholders' => ['date', 'holiday_name', 'resume_date', 'school_name'],
            ],

            // 9. Admission Status
            [
                'event_name' => 'admission_status',
                'channel' => 'email',
                'language' => 'en',
                'subject' => 'Admission Application {{application_number}} - {{status_text}}',
                'body_template' => "Dear {{applicant_name}},\n\nYour admission application #{{application_number}} for Class {{class_name}} at {{school_name}} has been updated to: {{status_text}}.\n\nNotes from Admissions Committee: {{remarks}}\n\nPlease log in to complete next steps: {{portal_url}}.\n\nAdmissions Team\n{{school_name}}",
                'placeholders' => ['applicant_name', 'application_number', 'class_name', 'status_text', 'remarks', 'portal_url', 'school_name'],
            ],
            [
                'event_name' => 'admission_status',
                'channel' => 'sms',
                'language' => 'en',
                'subject' => null,
                'body_template' => 'Admission Update: Application #{{application_number}} for {{applicant_name}} is {{status_text}}. Check details at {{portal_url}}. - {{school_name}}',
                'placeholders' => ['application_number', 'applicant_name', 'status_text', 'portal_url', 'school_name'],
            ],

            // 10. Parent-Teacher Meetings (PTM)
            [
                'event_name' => 'ptm_invitation',
                'channel' => 'whatsapp',
                'language' => 'en',
                'subject' => null,
                'body_template' => "🤝 *Parent-Teacher Meeting Invitation*\n\nDear Parent of *{{student_name}}*,\nYou are cordially invited for a Parent-Teacher Meeting on *{{meeting_date}}* at *{{meeting_time}}*.\n\nTeacher: {{teacher_name}}\nLocation/Link: {{room_or_link}}\n\nPlease confirm attendance in the Parent Portal: {{portal_url}}\n\n{{school_name}}",
                'placeholders' => ['student_name', 'meeting_date', 'meeting_time', 'teacher_name', 'room_or_link', 'portal_url', 'school_name'],
            ],
            [
                'event_name' => 'ptm_invitation',
                'channel' => 'email',
                'language' => 'en',
                'subject' => 'PTM Scheduled for {{student_name}} - {{meeting_date}}',
                'body_template' => "Dear Parent,\n\nA Parent-Teacher meeting has been scheduled for {{student_name}} (Class: {{class_name}}).\n\nDate: {{meeting_date}}\nTime Slot: {{meeting_time}}\nTeacher: {{teacher_name}}\nMode: {{meeting_type}} ({{room_or_link}})\n\nWe look forward to meeting you.\n\nAcademic Office\n{{school_name}}",
                'placeholders' => ['student_name', 'class_name', 'meeting_date', 'meeting_time', 'teacher_name', 'meeting_type', 'room_or_link', 'school_name'],
            ],

            // 11. Emergency Notifications
            [
                'event_name' => 'emergency_alert',
                'channel' => 'sms',
                'language' => 'en',
                'subject' => null,
                'body_template' => 'EMERGENCY ALERT: {{alert_message}}. All students are safe. Please monitor portal/phone for instructions. - {{school_name}}',
                'placeholders' => ['alert_message', 'school_name'],
            ],
            [
                'event_name' => 'emergency_alert',
                'channel' => 'whatsapp',
                'language' => 'en',
                'subject' => null,
                'body_template' => "🚨 *URGENT EMERGENCY ALERT*\n\n{{alert_message}}\n\n*Action Required:* {{action_required}}\nAll student safety protocols are actively enacted. Updates will be broadcast continuously.\n\n_{{school_name}}_",
                'placeholders' => ['alert_message', 'action_required', 'school_name'],
            ],
            [
                'event_name' => 'emergency_alert',
                'channel' => 'push',
                'language' => 'en',
                'subject' => 'EMERGENCY: {{alert_title}}',
                'body_template' => '{{alert_message}}. Follow official safety instructions.',
                'placeholders' => ['alert_title', 'alert_message'],
            ],
        ];

        foreach ($templates as $tpl) {
            NotificationTemplate::updateOrCreate(
                [
                    'tenant_id' => null, // Global default template
                    'event_name' => $tpl['event_name'],
                    'channel' => $tpl['channel'],
                    'language' => $tpl['language'],
                ],
                [
                    'subject' => $tpl['subject'],
                    'body_template' => $tpl['body_template'],
                    'placeholders' => $tpl['placeholders'],
                    'is_active' => true,
                ]
            );
        }
    }
}

