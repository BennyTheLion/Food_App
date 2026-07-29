<?php
declare(strict_types=1);

/** Streams a submission's field values as a downloadable UTF-8 CSV and exits. */
function kl_export_submission_csv(array $submission, array $rows): void
{
    $filename = 'submission-' . $submission['id'] . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel opens Hebrew correctly

    fputcsv($out, ['טופס', $submission['form_name']]);
    fputcsv($out, ['אתר', $submission['site_name']]);
    fputcsv($out, ['מטבח', $submission['kitchen_name']]);
    fputcsv($out, ['ממלא', $submission['filler_name']]);
    fputcsv($out, ['מועד', $submission['submitted_at']]);
    fputcsv($out, []);
    fputcsv($out, ['שדה', 'ערך']);
    foreach ($rows as $row) {
        fputcsv($out, [$row['label'], $row['value']]);
    }

    fclose($out);
}
