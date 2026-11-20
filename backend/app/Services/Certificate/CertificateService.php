<?php

namespace App\Services\Certificate;

use App\Models\AcademicSession;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Guardian;
use App\Models\SchoolBranch;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CertificateService
{
    /**
     * Default system templates.
     */
    public const DEFAULT_TEMPLATES = [
        'transfer' => [
            'title' => 'Transfer Certificate',
            'body_template' => 'This is to certify that {{student_name}}, son/daughter of {{father_name}}, bearing Admission Number {{admission_number}}, was a student of {{class_name}} during the academic session {{academic_year}}. He/She has cleared all school dues and his/her general conduct was {{conduct}}. He/She is leaving the school due to {{reason}}.',
        ],
        'bonafide' => [
            'title' => 'Bonafide Certificate',
            'body_template' => 'This is to certify that {{student_name}}, Admission Number {{admission_number}}, is a bonafide student of {{school_name}}, studying in {{class_name}} (Section: {{section_name}}) for the academic session {{academic_year}}.',
        ],
        'character' => [
            'title' => 'Character Certificate',
            'body_template' => 'This is to certify that {{student_name}}, Admission Number {{admission_number}}, has maintained exemplary conduct and character during his/her tenure at {{school_name}}. He/She bears a good moral character.',
        ],
        'study' => [
            'title' => 'Study Certificate',
            'body_template' => 'This is to certify that {{student_name}} studied at {{school_name}} from Class {{class_name}} during the period {{academic_year}}. His/Her date of birth as per official school records is {{dob}}.',
        ],
        'fee_clearance' => [
            'title' => 'Fee Clearance Certificate',
            'body_template' => 'This is to certify that all outstanding tuition, examination, transport, and ancillary fees for {{student_name}} (Admission No: {{admission_number}}, Class: {{class_name}}) have been cleared in full for the session {{academic_year}}.',
        ],
    ];

    /**
     * Create or retrieve template.
     */
    public function createTemplate(array $data): CertificateTemplate
    {
        return CertificateTemplate::create([
            'branch_id' => $data['branch_id'] ?? null,
            'type' => $data['type'],
            'title' => $data['title'],
            'body_template' => $data['body_template'],
            'header_content' => $data['header_content'] ?? null,
            'footer_content' => $data['footer_content'] ?? null,
            'logo_path' => $data['logo_path'] ?? null,
            'signature_title_1' => $data['signature_title_1'] ?? 'Class Teacher',
            'signature_title_2' => $data['signature_title_2'] ?? 'Principal',
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Issue a certificate to a student with dynamic placeholder replacement.
     */
    public function issueCertificate(array $data, ?User $issuer = null): Certificate
    {
        return DB::transaction(function () use ($data, $issuer) {
            $student = Student::withoutGlobalScopes()->with('guardians')->findOrFail($data['student_id']);
            $branch = SchoolBranch::withoutGlobalScopes()->findOrFail($data['branch_id']);

            $template = null;
            if (!empty($data['template_id'])) {
                $template = CertificateTemplate::findOrFail($data['template_id']);
            } else {
                $templateType = $data['type'] ?? 'bonafide';
                $template = CertificateTemplate::where('type', $templateType)->first();

                if (!$template) {
                    $def = self::DEFAULT_TEMPLATES[$templateType] ?? self::DEFAULT_TEMPLATES['bonafide'];
                    $template = CertificateTemplate::create([
                        'branch_id' => $branch->id,
                        'type' => $templateType,
                        'title' => $def['title'],
                        'body_template' => $def['body_template'],
                        'signature_title_1' => 'Prepared By',
                        'signature_title_2' => 'Principal',
                    ]);
                }
            }

            // Academic session & Enrollment
            $enrollment = StudentEnrollment::where('student_id', $student->id)
                ->with(['schoolClass', 'section', 'academicSession'])
                ->latest()
                ->first();

            $academicSessionId = $data['academic_session_id'] ?? $enrollment?->academic_session_id;

            // Generate unique Certificate Number
            $seq = rand(1000, 9999);
            $year = Carbon::now()->format('Y');
            $certNumber = 'CERT-' . strtoupper($branch->branch_code ?? 'MAIN') . '-' . strtoupper($template->type) . "-{$year}-{$seq}";

            // Generate unique Verification Code for QR Verification
            $verificationCode = 'VER-' . strtoupper(Str::random(12));

            // Dynamic Placeholders Mapping
            $guardianFather = $student->guardians->where('relation_type', 'father')->first();
            $guardianMother = $student->guardians->where('relation_type', 'mother')->first();

            $placeholders = [
                '{{student_name}}' => $student->first_name . ' ' . $student->last_name,
                '{{admission_number}}' => $student->admission_number,
                '{{class_name}}' => $enrollment?->schoolClass?->name ?? 'Standard',
                '{{section_name}}' => $enrollment?->section?->name ?? 'A',
                '{{academic_year}}' => $enrollment?->academicSession?->name ?? $year,
                '{{father_name}}' => $guardianFather ? ($guardianFather->first_name . ' ' . $guardianFather->last_name) : 'Guardian',
                '{{mother_name}}' => $guardianMother ? ($guardianMother->first_name . ' ' . $guardianMother->last_name) : 'Guardian',
                '{{dob}}' => $student->date_of_birth ? Carbon::parse($student->date_of_birth)->toFormattedDateString() : 'N/A',
                '{{issue_date}}' => Carbon::today()->toFormattedDateString(),
                '{{school_name}}' => $branch->school?->name ?? 'School Academy',
                '{{conduct}}' => $data['conduct'] ?? 'Good',
                '{{reason}}' => $data['reason'] ?? 'Parent relocation / higher studies',
            ];

            // Render content
            $rendered = str_replace(array_keys($placeholders), array_values($placeholders), $template->body_template);

            $certificate = Certificate::create([
                'branch_id' => $branch->id,
                'template_id' => $template->id,
                'student_id' => $student->id,
                'academic_session_id' => $academicSessionId,
                'certificate_number' => $certNumber,
                'type' => $template->type,
                'title' => $template->title,
                'rendered_content' => $rendered,
                'recipient_name' => $student->first_name . ' ' . $student->last_name,
                'issue_date' => $data['issue_date'] ?? Carbon::today()->toDateString(),
                'verification_code' => $verificationCode,
                'file_path' => 'certificates/' . $certNumber . '.pdf',
                'status' => 'issued',
                'issued_by' => $issuer?->id,
            ]);

            // Audit log
            AuditLog::create([
                'tenant_id' => $certificate->tenant_id,
                'school_id' => $branch->school_id,
                'branch_id' => $branch->id,
                'user_id' => $issuer?->id,
                'user_email' => $issuer?->email,
                'action' => 'certificate_issued',
                'auditable_type' => 'App\Models\Certificate',
                'auditable_id' => $certificate->id,
                'new_values' => [
                    'certificate_number' => $certNumber,
                    'type' => $template->type,
                    'student_id' => $student->id,
                    'verification_code' => $verificationCode,
                ],
                'created_at' => Carbon::now(),
            ]);

            return $certificate->fresh(['student', 'template', 'branch']);
        });
    }

    /**
     * Revoke an issued certificate.
     */
    public function revokeCertificate(string $certificateId, string $reason, ?User $revoker = null): Certificate
    {
        return DB::transaction(function () use ($certificateId, $reason, $revoker) {
            $certificate = Certificate::findOrFail($certificateId);

            if ($certificate->status === 'revoked') {
                throw new InvalidArgumentException("Certificate is already revoked.");
            }

            $certificate->update([
                'status' => 'revoked',
                'revoked_at' => Carbon::now(),
                'revocation_reason' => $reason,
                'revoked_by' => $revoker?->id,
            ]);

            // Audit log
            AuditLog::create([
                'tenant_id' => $certificate->tenant_id,
                'user_id' => $revoker?->id,
                'user_email' => $revoker?->email,
                'action' => 'certificate_revoked',
                'auditable_type' => 'App\Models\Certificate',
                'auditable_id' => $certificate->id,
                'new_values' => [
                    'certificate_number' => $certificate->certificate_number,
                    'reason' => $reason,
                    'revoked_at' => Carbon::now()->toIso8601String(),
                ],
                'created_at' => Carbon::now(),
            ]);

            return $certificate->fresh(['revoker']);
        });
    }

    /**
     * Public QR Code Verification endpoint.
     * Can be invoked without authentication to authenticate certificate validity.
     */
    public function verifyCertificate(string $verificationCode): array
    {
        $cert = Certificate::withoutGlobalScopes()
            ->with(['student', 'branch.school'])
            ->where('verification_code', trim($verificationCode))
            ->first();

        if (!$cert) {
            return [
                'is_valid' => false,
                'message' => 'Invalid or unrecognized certificate verification code.',
            ];
        }

        if ($cert->status === 'revoked') {
            return [
                'is_valid' => false,
                'is_revoked' => true,
                'certificate_number' => $cert->certificate_number,
                'title' => $cert->title,
                'recipient_name' => $cert->recipient_name,
                'revoked_at' => $cert->revoked_at?->toFormattedDateString(),
                'revocation_reason' => $cert->revocation_reason,
                'message' => 'This certificate was officially REVOKED by the school authority.',
            ];
        }

        return [
            'is_valid' => true,
            'certificate_number' => $cert->certificate_number,
            'title' => $cert->title,
            'type' => $cert->type,
            'recipient_name' => $cert->recipient_name,
            'issue_date' => $cert->issue_date->toFormattedDateString(),
            'school_name' => $cert->branch?->school?->name ?? 'Verified Institution',
            'branch_name' => $cert->branch?->name ?? 'Main Campus',
            'rendered_summary' => $cert->rendered_content,
            'status' => 'verified_authentic',
        ];
    }

    /**
     * Generate printable PDF / HTML format with security watermarks.
     */
    public function renderCertificateHtml(Certificate $certificate): string
    {
        $watermark = $certificate->status === 'revoked' ? 'REVOKED' : 'OFFICIAL';
        $color = $certificate->status === 'revoked' ? '#dc2626' : '#2563eb';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{$certificate->title} - {$certificate->certificate_number}</title>
    <style>
        body { font-family: 'Georgia', serif; padding: 40px; color: #1e293b; background: #fff; }
        .cert-border { border: 6px double #334155; padding: 30px; position: relative; }
        .watermark { position: absolute; top: 40%; left: 25%; font-size: 80px; color: rgba(100, 116, 139, 0.08); transform: rotate(-30deg); font-weight: bold; }
        .header { text-align: center; border-bottom: 2px solid #cbd5e1; padding-bottom: 15px; margin-bottom: 25px; }
        .header h1 { font-size: 26px; text-transform: uppercase; color: {$color}; margin: 0; }
        .header h2 { font-size: 16px; font-weight: normal; margin-top: 5px; color: #475569; }
        .cert-number { font-size: 12px; font-family: monospace; color: #64748b; text-align: right; margin-bottom: 15px; }
        .body-text { font-size: 16px; line-height: 1.8; text-align: justify; margin: 35px 0; }
        .signatures { display: flex; justify-content: space-between; margin-top: 60px; padding: 0 40px; }
        .sig-block { text-align: center; border-top: 1px solid #475569; width: 180px; padding-top: 5px; font-size: 13px; font-weight: bold; }
        .qr-section { margin-top: 30px; font-size: 11px; color: #64748b; font-family: sans-serif; text-align: center; }
        .qr-code { font-family: monospace; font-weight: bold; color: #1e293b; background: #f1f5f9; padding: 4px 8px; border-radius: 4px; display: inline-block; }
    </style>
</head>
<body onload="window.print()">
    <div class="cert-border">
        <div class="watermark">{$watermark}</div>
        <div class="cert-number">Certificate No: {$certificate->certificate_number}</div>
        <div class="header">
            <h1>{$certificate->title}</h1>
            <h2>OFFICIAL ACADEMIC CREDENTIAL</h2>
        </div>
        <div class="body-text">
            {$certificate->rendered_content}
        </div>
        <div class="signatures">
            <div class="sig-block">Prepared By</div>
            <div class="sig-block">Principal / Headmaster</div>
        </div>
        <div class="qr-section">
            <p>Verification Code: <span class="qr-code">{$certificate->verification_code}</span></p>
            <p>Scan QR or visit school portal to verify authenticity.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}

