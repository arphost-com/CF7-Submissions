<?php
// wp eval-file helper: apply /tmp/field-map.txt to cf7dbgs settings.
$s = get_option( 'cf7dbgs_settings' );
$s['field_map'] = trim( (string) file_get_contents( '/tmp/field-map.txt' ) );
update_option( 'cf7dbgs_settings', $s );
echo "map updated:\n" . $s['field_map'] . "\n";
