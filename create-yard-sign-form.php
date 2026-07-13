<?php
// wp eval-file helper: create (or update) the "Yard Sign Request" CF7 form.

$form_template = <<<'FORM'
<div class="form-group"><label>Your Name <span class="required">*</span>
[text* your-name autocomplete:name]</label></div>
<div class="form-group"><label>Phone Number <span class="required">*</span>
[tel* your-phone autocomplete:tel]</label></div>
<div class="form-group"><label>Your Email <span class="required">*</span>
[email* your-email autocomplete:email]</label></div>
<div class="form-group"><label>Sign Location Type <span class="required">*</span>
[checkbox* yard-sign-type use_label_element "House" "Business"]</label></div>
<div class="form-group"><label>Your Message
[textarea your-message placeholder "Address or notes for sign delivery"]</label></div>
<div class="form-submit">[submit class:submit-btn "Request Yard Sign"]</div>
FORM;

$mail_body = <<<'MAIL'
Yard sign request:

Name: [your-name]
Phone: [your-phone]
Email: [your-email]
Sign type: [yard-sign-type]

Message:
[your-message]

--
Sent from the yard sign form at [_site_url]
MAIL;

$existing = get_posts( array(
    'post_type'   => 'wpcf7_contact_form',
    'title'       => 'Yard Sign Request',
    'post_status' => 'any',
    'numberposts' => 1,
) );

if ( $existing ) {
    $form = WPCF7_ContactForm::get_instance( $existing[0]->ID );
} else {
    $form = WPCF7_ContactForm::get_template( array( 'title' => 'Yard Sign Request' ) );
}

$props = $form->get_properties();
$props['form'] = $form_template;
$props['mail']['subject']   = '[_site_title] - Yard Sign Request: [your-name]';
$props['mail']['body']      = $mail_body;
$props['mail']['recipient'] = 'info@vic4hd47.com';
$props['mail']['additional_headers'] = 'Reply-To: [your-email]';
$form->set_properties( $props );
$id = $form->save();

echo "Yard Sign Request form ID: " . $form->id() . "\n";
echo 'Shortcode: [contact-form-7 id="' . $form->id() . '" title="Yard Sign Request"]' . "\n";
