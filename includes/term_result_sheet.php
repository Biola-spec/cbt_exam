<?php
function termResultPeriods(mysqli $mysqli, int $studentId): array {
    $stmt = $mysqli->prepare("SELECT rp.id, sess.name AS session_name, rp.term, c.name AS class_name,
            COUNT(DISTINCT ts.subject_id) AS subject_count
        FROM term_scores ts
        JOIN result_periods rp ON rp.id=ts.period_id
        JOIN academic_sessions sess ON sess.id=rp.session_id
        JOIN classes c ON c.id=rp.class_id
        WHERE ts.student_id=? AND ts.release_status='approved'
        GROUP BY rp.id, sess.name, rp.term, c.name
        ORDER BY sess.name DESC, rp.term DESC");
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function termResultSheetLoad(mysqli $mysqli, int $studentId, int $periodId): ?array {
    $stmt = $mysqli->prepare("SELECT rp.id AS period_id, rp.session_id, rp.term, sess.name AS session_name,
            c.id AS class_id, c.name AS class_name, u.full_name, u.admission_number, u.passport,
            COALESCE(ctr.remark, '') AS class_teacher_remark,
            COALESCE(trs.school_days_open, 0) AS school_days_open,
            cas.student_id AS attendance_summary_student_id, cas.days_present,
            COALESCE(sc.school_name, '') AS school_name, COALESCE(sc.logo_path, '') AS logo_path,
            COALESCE(sc.head_signature_path, '') AS head_signature_path,
            COALESCE(sc.principal_signature_path, '') AS principal_signature_path,
            COALESCE(sc.school_stamp_path, '') AS school_stamp_path,
            head_signatory.title AS head_teacher_title, head_user.full_name AS head_teacher_name,
            head_signatory.signature_path AS head_teacher_staff_signature_path,
            principal_signatory.title AS principal_title, principal_user.full_name AS principal_name,
            principal_signatory.signature_path AS principal_staff_signature_path
        FROM result_periods rp
        JOIN academic_sessions sess ON sess.id=rp.session_id
        JOIN classes c ON c.id=rp.class_id
        JOIN term_scores ts ON ts.period_id=rp.id AND ts.student_id=? AND ts.release_status='approved'
        JOIN users u ON u.id=ts.student_id
        LEFT JOIN class_term_remarks ctr ON ctr.class_id=rp.class_id AND ctr.session_id=rp.session_id AND ctr.term=rp.term AND ctr.student_id=ts.student_id
        LEFT JOIN term_report_settings trs ON trs.session_id=rp.session_id AND trs.term=rp.term
        LEFT JOIN class_student_attendance_summary cas ON cas.class_id=rp.class_id AND cas.session_id=rp.session_id AND cas.term=rp.term AND cas.student_id=ts.student_id
        LEFT JOIN class_report_signatories head_signatory ON head_signatory.class_id=rp.class_id AND head_signatory.position='head_teacher'
        LEFT JOIN users head_user ON head_user.id=head_signatory.staff_id
        LEFT JOIN class_report_signatories principal_signatory ON principal_signatory.class_id=rp.class_id AND principal_signatory.position='principal'
        LEFT JOIN users principal_user ON principal_user.id=principal_signatory.staff_id
        LEFT JOIN school_config sc ON sc.id=(SELECT MAX(id) FROM school_config)
        WHERE rp.id=?
        LIMIT 1");
    $stmt->bind_param('ii', $studentId, $periodId);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$report) return null;

    $signatoryPosition = $report['principal_name'] ? 'principal' : 'head_teacher';
    $signatoryTitle = $signatoryPosition === 'principal' ? ($report['principal_title'] ?: 'Principal') : ($report['head_teacher_title'] ?: 'Head Teacher');
    $signatoryName = $signatoryPosition === 'principal' ? ($report['principal_name'] ?: '') : ($report['head_teacher_name'] ?: '');
    $signatoryStaffSignature = $signatoryPosition === 'principal' ? ($report['principal_staff_signature_path'] ?? '') : ($report['head_teacher_staff_signature_path'] ?? '');
    $signatorySchoolSignature = $signatoryPosition === 'principal' ? $report['principal_signature_path'] : $report['head_signature_path'];
    $authorityRemark = '';
    $stmt = $mysqli->prepare('SELECT remark FROM class_report_remarks WHERE class_id=? AND session_id=? AND term=? AND student_id=? AND position=?');
    $stmt->bind_param('iiiis', $report['class_id'], $report['session_id'], $report['term'], $studentId, $signatoryPosition);
    $stmt->execute();
    $remarkRow = $stmt->get_result()->fetch_assoc();
    if ($remarkRow) $authorityRemark = $remarkRow['remark'];
    $stmt->close();

    $stmt = $mysqli->prepare("SELECT COALESCE(crs.ca_count,3) AS ca_count, COALESCE(crs.exam_count,1) AS exam_count
        FROM result_periods rp
        LEFT JOIN class_result_settings crs ON crs.class_id=rp.class_id AND crs.session_id=rp.session_id
        WHERE rp.id=?");
    $stmt->bind_param('i', $periodId);
    $stmt->execute();
    $settings = $stmt->get_result()->fetch_assoc() ?: ['ca_count' => 3, 'exam_count' => 1];
    $stmt->close();
    $counts = ['ca' => (int)$settings['ca_count'], 'exam' => (int)$settings['exam_count']];

    $components = ['ca' => [], 'exam' => []];
    $stmt = $mysqli->prepare("SELECT component_type, component_number, label, max_score
        FROM class_result_component_names WHERE class_id=? AND session_id=(SELECT session_id FROM result_periods WHERE id=?)");
    $stmt->bind_param('ii', $report['class_id'], $periodId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($component = $res->fetch_assoc()) {
        $components[$component['component_type']][(int)$component['component_number']] = [
            'label' => $component['label'],
            'maximum' => (float)$component['max_score'],
        ];
    }
    $stmt->close();
    foreach ($counts as $type => $count) {
        for ($number = 1; $number <= $count; $number++) {
            if (!isset($components[$type][$number])) {
                $components[$type][$number] = [
                    'label' => ($type === 'ca' ? 'CA ' : 'Exam ') . $number,
                    'maximum' => 100.0,
                ];
            }
        }
        ksort($components[$type]);
    }

    $stmt = $mysqli->prepare("SELECT subj.id AS subject_id, subj.name AS subject_name,
            tsi.component_type, tsi.component_number, tsi.score
        FROM term_scores ts
        JOIN subjects subj ON subj.id=ts.subject_id
        JOIN term_score_items tsi ON tsi.term_score_id=ts.id
        WHERE ts.period_id=? AND ts.student_id=? AND ts.release_status='approved'
        ORDER BY subj.name, subj.id,
            CASE WHEN tsi.component_type='ca' THEN 0 ELSE 1 END, tsi.component_number");
    $stmt->bind_param('ii', $periodId, $studentId);
    $stmt->execute();
    $res = $stmt->get_result();
    $subjects = [];
    while ($row = $res->fetch_assoc()) {
        $subjectId = (int)$row['subject_id'];
        if (!isset($subjects[$subjectId])) {
            $subjects[$subjectId] = [
                'name' => $row['subject_name'],
                'scores' => ['ca' => [], 'exam' => []],
                'total' => 0.0,
                'maximum' => 0.0,
            ];
        }
        $type = $row['component_type'];
        $number = (int)$row['component_number'];
        $score = (float)$row['score'];
        $subjects[$subjectId]['scores'][$type][$number] = $score;
        $subjects[$subjectId]['total'] += $score;
    }
    $stmt->close();

    $componentList = [];
    foreach (['ca', 'exam'] as $type) {
        foreach ($components[$type] as $number => $component) {
            $componentList[] = ['type' => $type, 'number' => $number] + $component;
        }
    }
    $overallTotal = 0.0;
    $overallMaximum = 0.0;
    $percentageTotal = 0.0;
    $gradeScale = [];
    $gradeResult = $mysqli->query('SELECT minimum_score, maximum_score, grade_letter, remark FROM grade_settings ORDER BY minimum_score');
    if ($gradeResult) while ($grade = $gradeResult->fetch_assoc()) $gradeScale[] = $grade;
    foreach ($subjects as &$subject) {
        foreach ($componentList as $component) {
            $subject['maximum'] += $component['maximum'];
        }
        $subject['percentage'] = $subject['maximum'] > 0 ? ($subject['total'] / $subject['maximum']) * 100 : 0;
        $subject['grade'] = '';
        foreach ($gradeScale as $grade) {
            if ($subject['percentage'] >= (float)$grade['minimum_score'] && $subject['percentage'] <= (float)$grade['maximum_score']) {
                $subject['grade'] = $grade['grade_letter'];
                break;
            }
        }
        $overallTotal += $subject['total'];
        $overallMaximum += $subject['maximum'];
        $percentageTotal += $subject['percentage'];
    }
    unset($subject);
    $overallAverage = count($subjects) ? $percentageTotal / count($subjects) : 0;
    $overallGrade = '';
    foreach ($gradeScale as $grade) {
        if ($overallAverage >= (float)$grade['minimum_score'] && $overallAverage <= (float)$grade['maximum_score']) {
            $overallGrade = $grade['grade_letter'];
            break;
        }
    }

    $ratings = ['psychomotor' => [], 'locomotor' => []];
    $stmt = $mysqli->prepare('SELECT crc.domain, crc.label, csr.score FROM class_student_ratings csr JOIN class_report_categories crc ON crc.id=csr.category_id WHERE csr.class_id=? AND csr.session_id=? AND csr.term=? AND csr.student_id=? ORDER BY crc.domain, crc.sort_order, crc.label');
    $stmt->bind_param('iiii', $report['class_id'], $report['session_id'], $report['term'], $studentId);
    $stmt->execute();
    $ratingRows = $stmt->get_result();
    while ($rating = $ratingRows->fetch_assoc()) $ratings[$rating['domain']][] = ['label' => $rating['label'], 'score' => (int)$rating['score']];
    $stmt->close();

    return [
        'period_id' => (int)$report['period_id'],
        'student' => [
            'name' => $report['full_name'],
            'admission_number' => $report['admission_number'] ?: 'Not recorded',
            'passport' => $report['passport'] ?? '',
        ],
        'school' => [
            'name' => $report['school_name'] ?: 'School',
            'logo_path' => $report['logo_path'],
            'signatory_signature_path' => $signatoryStaffSignature ?: ($signatoryName ? '' : $signatorySchoolSignature),
            'stamp_path' => $report['school_stamp_path'],
        ],
        'signatory' => ['position' => $signatoryPosition, 'title' => $signatoryTitle, 'name' => $signatoryName],
        'session' => $report['session_name'],
        'term' => (int)$report['term'],
        'class' => $report['class_name'],
        'class_teacher_remark' => $report['class_teacher_remark'],
        'authority_remark' => $authorityRemark,
        'attendance' => [
            'school_days_open' => (int)$report['school_days_open'],
            'days_present' => $report['attendance_summary_student_id'] !== null ? (int)$report['days_present'] : null,
            'days_absent' => $report['attendance_summary_student_id'] !== null ? max(0, (int)$report['school_days_open'] - (int)$report['days_present']) : null,
        ],
        'ratings' => $ratings,
        'components' => $componentList,
        'subjects' => array_values($subjects),
        'summary' => [
            'subjects' => count($subjects),
            'total' => $overallTotal,
            'maximum' => $overallMaximum,
            'average' => $overallAverage,
            'grade' => $overallGrade,
        ],
    ];
}
