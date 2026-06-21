<?php

namespace Database\Seeders;

use App\Models\CertificateTemplate;
use App\Models\Diocese;
use App\Models\User;
use Illuminate\Database\Seeder;

class CertificateTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $diocese = Diocese::first();
        $user = User::where('email', 'superadmin@msoc-europe.org')->first() 
            ?? User::where('email', 'admin@msoc-europe.org')->first();

        if (!$diocese || !$user) {
            return;
        }

        $templates = [
            [
                'name' => 'Default Membership Certificate',
                'certificate_type' => 'membership',
                'language' => 'en',
                'html_template' => '<html>
<head>
<style>
    body { font-family: sans-serif; text-align: center; padding: 40px; border: 10px double #581c1c; }
    h1 { color: #581c1c; font-size: 32px; margin-bottom: 5px; font-weight: bold; }
    h2 { color: #d97706; font-size: 18px; text-transform: uppercase; margin-top: 0; margin-bottom: 30px; letter-spacing: 2px; }
    h3 { font-size: 24px; text-decoration: underline; margin-bottom: 40px; }
    .content { font-size: 18px; line-height: 1.8; margin: 40px 0; text-align: justify; padding: 0 40px; }
    .meta-box { margin-top: 50px; font-size: 12px; color: #666; border-top: 1px solid #ccc; padding-top: 10px; }
    .footer { margin-top: 80px; width: 100%; }
    .sig-table { width: 100%; border: none; }
    .sig-table td { width: 50%; text-align: center; font-size: 14px; }
</style>
</head>
<body>
    <h1>Malankara Syrian Orthodox Church</h1>
    <h2>Diocese of Europe</h2>
    <h3>MEMBERSHIP CERTIFICATE</h3>
    <div class="content">
        This is to certify that <strong>{{member_full_name}}</strong> (Baptismal Name: <em>{{baptism_name}}</em>),
        spouse/child of the <strong>{{family_name}}</strong> family, is a registered and active member of 
        <strong>{{church_name}}</strong> under the Diocese of Europe. To the best of our knowledge, they are 
        in full communion with the church.
    </div>
    <div class="meta-box">
        Certificate Number: {{certificate_number}} | Verification Code: {{verification_code}}
    </div>
    <table class="sig-table" style="margin-top: 60px;">
        <tr>
            <td>
                _____________________________<br>
                <strong>Vicar / Assistant Priest</strong><br>
                {{priest_name}}
            </td>
            <td>
                _____________________________<br>
                <strong>Date of Issue</strong><br>
                {{issued_date}}
            </td>
        </tr>
    </table>
</body>
</html>',
            ],
            [
                'name' => 'Default Baptism Certificate',
                'certificate_type' => 'baptism',
                'language' => 'en',
                'html_template' => '<html>
<head>
<style>
    body { font-family: sans-serif; text-align: center; padding: 40px; border: 10px double #1e3a8a; }
    h1 { color: #1e3a8a; font-size: 32px; margin-bottom: 5px; font-weight: bold; }
    h2 { color: #d97706; font-size: 18px; text-transform: uppercase; margin-top: 0; margin-bottom: 30px; letter-spacing: 2px; }
    h3 { font-size: 24px; text-decoration: underline; margin-bottom: 40px; }
    .content { font-size: 18px; line-height: 1.8; margin: 40px 0; text-align: justify; padding: 0 40px; }
    .meta-box { margin-top: 50px; font-size: 12px; color: #666; border-top: 1px solid #ccc; padding-top: 10px; }
    .footer { margin-top: 80px; width: 100%; }
    .sig-table { width: 100%; border: none; }
    .sig-table td { width: 50%; text-align: center; font-size: 14px; }
</style>
</head>
<body>
    <h1>Malankara Syrian Orthodox Church</h1>
    <h2>Diocese of Europe</h2>
    <h3>BAPTISM CERTIFICATE</h3>
    <div class="content">
        This is to certify that <strong>{{member_full_name}}</strong> has received the Holy Sacrament of 
        Baptism (Christian Name: <strong>{{baptism_name}}</strong>) at <strong>{{church_name}}</strong>. 
        The holy sacrament was officiated by <strong>{{priest_name}}</strong>.
    </div>
    <div class="meta-box">
        Certificate Number: {{certificate_number}} | Verification Code: {{verification_code}}
    </div>
    <table class="sig-table" style="margin-top: 60px;">
        <tr>
            <td>
                _____________________________<br>
                <strong>Vicar / Priest</strong><br>
                {{priest_name}}
            </td>
            <td>
                _____________________________<br>
                <strong>Date of Issue</strong><br>
                {{issued_date}}
            </td>
        </tr>
    </table>
</body>
</html>',
            ],
            [
                'name' => 'Default Marriage Certificate',
                'certificate_type' => 'marriage',
                'language' => 'en',
                'html_template' => '<html>
<head>
<style>
    body { font-family: sans-serif; text-align: center; padding: 40px; border: 10px double #581c1c; }
    h1 { color: #581c1c; font-size: 32px; margin-bottom: 5px; font-weight: bold; }
    h2 { color: #d97706; font-size: 18px; text-transform: uppercase; margin-top: 0; margin-bottom: 30px; letter-spacing: 2px; }
    h3 { font-size: 24px; text-decoration: underline; margin-bottom: 40px; }
    .content { font-size: 18px; line-height: 1.8; margin: 40px 0; text-align: justify; padding: 0 40px; }
    .meta-box { margin-top: 50px; font-size: 12px; color: #666; border-top: 1px solid #ccc; padding-top: 10px; }
    .footer { margin-top: 80px; width: 100%; }
    .sig-table { width: 100%; border: none; }
    .sig-table td { width: 50%; text-align: center; font-size: 14px; }
</style>
</head>
<body>
    <h1>Malankara Syrian Orthodox Church</h1>
    <h2>Diocese of Europe</h2>
    <h3>MARRIAGE CERTIFICATE</h3>
    <div class="content">
        This is to certify that the Holy Sacrament of Holy Matrimony was solemnized between 
        <strong>{{member_full_name}}</strong> and their spouse at <strong>{{church_name}}</strong> on this day, 
        officiated by <strong>{{priest_name}}</strong>, in the presence of witnesses.
    </div>
    <div class="meta-box">
        Certificate Number: {{certificate_number}} | Verification Code: {{verification_code}}
    </div>
    <table class="sig-table" style="margin-top: 60px;">
        <tr>
            <td>
                _____________________________<br>
                <strong>Vicar / Priest</strong><br>
                {{priest_name}}
            </td>
            <td>
                _____________________________<br>
                <strong>Date of Issue</strong><br>
                {{issued_date}}
            </td>
        </tr>
    </table>
</body>
</html>',
            ],
            [
                'name' => 'Default Death Certificate',
                'certificate_type' => 'death',
                'language' => 'en',
                'html_template' => '<html>
<head>
<style>
    body { font-family: sans-serif; text-align: center; padding: 40px; border: 10px double #1e293b; }
    h1 { color: #1e293b; font-size: 32px; margin-bottom: 5px; font-weight: bold; }
    h2 { color: #475569; font-size: 18px; text-transform: uppercase; margin-top: 0; margin-bottom: 30px; letter-spacing: 2px; }
    h3 { font-size: 24px; text-decoration: underline; margin-bottom: 40px; }
    .content { font-size: 18px; line-height: 1.8; margin: 40px 0; text-align: justify; padding: 0 40px; }
    .meta-box { margin-top: 50px; font-size: 12px; color: #666; border-top: 1px solid #ccc; padding-top: 10px; }
    .footer { margin-top: 80px; width: 100%; }
    .sig-table { width: 100%; border: none; }
    .sig-table td { width: 50%; text-align: center; font-size: 14px; }
</style>
</head>
<body>
    <h1>Malankara Syrian Orthodox Church</h1>
    <h2>Diocese of Europe</h2>
    <h3>FUNERAL & DEATH CERTIFICATE</h3>
    <div class="content">
        This is to certify that <strong>{{member_full_name}}</strong> (Baptismal Name: <em>{{baptism_name}}</em>), 
        member of <strong>{{church_name}}</strong>, departed to eternal peace and was laid to rest according to 
        the rites of the Malankara Syrian Orthodox Church.
    </div>
    <div class="meta-box">
        Certificate Number: {{certificate_number}} | Verification Code: {{verification_code}}
    </div>
    <table class="sig-table" style="margin-top: 60px;">
        <tr>
            <td>
                _____________________________<br>
                <strong>Vicar / Priest</strong><br>
                {{priest_name}}
            </td>
            <td>
                _____________________________<br>
                <strong>Date of Issue</strong><br>
                {{issued_date}}
            </td>
        </tr>
    </table>
</body>
</html>',
            ],
            [
                'name' => 'Default Recommendation Letter',
                'certificate_type' => 'recommendation',
                'language' => 'en',
                'html_template' => '<html>
<head>
<style>
    body { font-family: sans-serif; padding: 40px; line-height: 1.8; font-size: 16px; color: #333; }
    .header { text-align: center; margin-bottom: 40px; border-bottom: 2px solid #581c1c; padding-bottom: 20px; }
    h1 { color: #581c1c; margin: 0; font-size: 28px; }
    h2 { color: #d97706; margin: 5px 0 0 0; font-size: 16px; text-transform: uppercase; }
    .date { text-align: right; margin-bottom: 30px; }
    .salutation { font-weight: bold; margin-bottom: 20px; }
    .content { text-align: justify; margin-bottom: 40px; }
    .signature { margin-top: 60px; text-align: right; padding-right: 40px; }
    .meta-box { margin-top: 50px; font-size: 11px; color: #777; }
</style>
</head>
<body>
    <div class="header">
        <h1>Malankara Syrian Orthodox Church</h1>
        <h2>Diocese of Europe</h2>
    </div>
    <div class="date">
        Date: {{issued_date}}
    </div>
    <div class="salutation">
        TO WHOMSOEVER IT MAY CONCERN
    </div>
    <div class="content">
        This is to recommend <strong>{{member_full_name}}</strong>, who is a member in good standing of 
        <strong>{{church_name}}</strong>. We confirm that they are active in parish life and recommend them 
        for participation in spiritual and ecclesiastical events within the Holy Church.
    </div>
    <div class="signature">
        _____________________________<br>
        <strong>Vicar / Priest</strong><br>
        {{priest_name}}
    </div>
    <div class="meta-box">
        Ref No: {{certificate_number}} | Verification Code: {{verification_code}}
    </div>
</body>
</html>',
            ],
            [
                'name' => 'Default No Objection Certificate',
                'certificate_type' => 'no_objection',
                'language' => 'en',
                'html_template' => '<html>
<head>
<style>
    body { font-family: sans-serif; padding: 40px; line-height: 1.8; font-size: 16px; color: #333; }
    .header { text-align: center; margin-bottom: 40px; border-bottom: 2px solid #581c1c; padding-bottom: 20px; }
    h1 { color: #581c1c; margin: 0; font-size: 28px; }
    h2 { color: #d97706; margin: 5px 0 0 0; font-size: 16px; text-transform: uppercase; }
    .date { text-align: right; margin-bottom: 30px; }
    .salutation { font-weight: bold; margin-bottom: 20px; }
    .content { text-align: justify; margin-bottom: 40px; }
    .signature { margin-top: 60px; text-align: right; padding-right: 40px; }
    .meta-box { margin-top: 50px; font-size: 11px; color: #777; }
</style>
</head>
<body>
    <div class="header">
        <h1>Malankara Syrian Orthodox Church</h1>
        <h2>Diocese of Europe</h2>
    </div>
    <div class="date">
        Date: {{issued_date}}
    </div>
    <div class="salutation">
        NO OBJECTION CERTIFICATE
    </div>
    <div class="content">
        We have no objection to <strong>{{member_full_name}}</strong> receiving sacraments or performing ecclesiastical 
        actions outside of <strong>{{church_name}}</strong>, as requested. They remain a member in good ecclesiastical standing.
    </div>
    <div class="signature">
        _____________________________<br>
        <strong>Vicar / Priest</strong><br>
        {{priest_name}}
    </div>
    <div class="meta-box">
        Ref No: {{certificate_number}} | Verification Code: {{verification_code}}
    </div>
</body>
</html>',
            ],
            [
                'name' => 'Default Custom Certificate',
                'certificate_type' => 'custom',
                'language' => 'en',
                'html_template' => '<html>
<head>
<style>
    body { font-family: sans-serif; text-align: center; padding: 40px; border: 5px solid #d97706; }
    h1 { color: #581c1c; font-size: 30px; margin-bottom: 5px; }
    h2 { color: #d97706; font-size: 16px; text-transform: uppercase; margin-bottom: 30px; }
    h3 { font-size: 22px; margin-bottom: 35px; }
    .content { font-size: 18px; line-height: 1.8; margin: 40px 0; }
    .meta-box { margin-top: 50px; font-size: 12px; color: #777; }
</style>
</head>
<body>
    <h1>Malankara Syrian Orthodox Church</h1>
    <h2>Diocese of Europe</h2>
    <h3>CERTIFICATE</h3>
    <div class="content">
        This certificate is awarded to <strong>{{member_full_name}}</strong> of <strong>{{church_name}}</strong>.
    </div>
    <div class="meta-box">
        No: {{certificate_number}} | Code: {{verification_code}} | Date: {{issued_date}}
    </div>
</body>
</html>',
            ],
        ];

        foreach ($templates as $t) {
            CertificateTemplate::create(array_merge($t, [
                'diocese_id' => $diocese->id,
                'is_active' => true,
                'created_by' => $user->id,
            ]));
        }
    }
}
