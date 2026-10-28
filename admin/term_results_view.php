<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../includes/term_results.php';
requireLogin();
if (getUserRole() !== 'admin') { header('Location: ../login.php'); exit(); }
$mysqli=getDBConnection();
$periodId=(int)($_GET['period_id']??0);
$period=null; $scores=[]; $error=''; $componentMaxima=['ca'=>[],'exam'=>[]];
$releaseCounts=['approved'=>0,'submitted'=>0,'draft'=>0,'recalled'=>0];
$message=trim($_GET['message']??'');
try {
    if(!$mysqli) throw new RuntimeException('Database connection failed.');
    $stmt=$mysqli->prepare('SELECT rp.id,rp.session_id,rp.class_id,rp.term,rp.status,c.name AS class_name,s.name AS session_name,COALESCE(crs.ca_count,3) AS ca_count,COALESCE(crs.exam_count,1) AS exam_count
        FROM result_periods rp JOIN classes c ON c.id=rp.class_id JOIN academic_sessions s ON s.id=rp.session_id
        LEFT JOIN class_result_settings crs ON crs.class_id=rp.class_id AND crs.session_id=rp.session_id WHERE rp.id=?');
    $stmt->bind_param('i',$periodId);$stmt->execute();$period=$stmt->get_result()->fetch_assoc();$stmt->close();
    if(!$period) throw new RuntimeException('Result sheet not found.');
    foreach(['ca'=>(int)$period['ca_count'],'exam'=>(int)$period['exam_count']] as $type=>$count) for($i=1;$i<=$count;$i++) {
        $componentMaxima[$type][$i]=100.0;
    }
    $stmt=$mysqli->prepare('SELECT component_type,component_number,label,max_score FROM class_result_component_names WHERE class_id=? AND session_id=?');
    $stmt->bind_param('ii',$period['class_id'],$period['session_id']);$stmt->execute();$configResult=$stmt->get_result();
    while($item=$configResult->fetch_assoc()) {
        $componentMaxima[$item['component_type']][(int)$item['component_number']]=(float)$item['max_score'];
    }
    $stmt->close();
    $stmt=$mysqli->prepare("SELECT ts.id AS term_score_id,ts.student_id,ts.subject_id,ts.release_status,u.full_name,subj.name AS subject_name,tsi.component_type,tsi.component_number,tsi.score,
        COALESCE(cn.label,IF(tsi.component_type='ca',CONCAT('CA ',tsi.component_number),CONCAT('Exam ',tsi.component_number))) AS component_label,
        COALESCE(cn.max_score,100) AS component_maximum
        FROM term_scores ts JOIN users u ON u.id=ts.student_id JOIN subjects subj ON subj.id=ts.subject_id
        LEFT JOIN term_score_items tsi ON tsi.term_score_id=ts.id
        LEFT JOIN class_result_component_names cn ON cn.class_id=? AND cn.session_id=? AND cn.component_type=tsi.component_type AND cn.component_number=tsi.component_number
        WHERE ts.period_id=? ORDER BY u.full_name,subj.name,tsi.component_type,tsi.component_number");
    $stmt->bind_param('iii',$period['class_id'],$period['session_id'],$periodId);$stmt->execute();$res=$stmt->get_result();
    while($row=$res->fetch_assoc()) {
        $key=$row['term_score_id'];
        if(!isset($scores[$key])) {
            $scores[$key]=['id'=>(int)$row['term_score_id'],'student_id'=>(int)$row['student_id'],'student'=>$row['full_name'],'subject'=>$row['subject_name'],'ca'=>[],'exam'=>[],'total'=>0,'filled'=>0,'maximum'=>array_sum($componentMaxima['ca'])+array_sum($componentMaxima['exam']),'release_status'=>$row['release_status']];
            $releaseCounts[$row['release_status']]=(int)($releaseCounts[$row['release_status']]??0)+1;
        }
        if($row['component_type']) {
            $type=$row['component_type'];$number=(int)$row['component_number'];$value=$row['score']===null?null:(float)$row['score'];
            $scores[$key][$type][$number]=['label'=>$row['component_label'],'score'=>$value,'maximum'=>(float)$row['component_maximum']];
            if($value!==null){$scores[$key]['total']+=$value;$scores[$key]['filled']++;}
        }
    }
    $stmt->close();
    $requiredPerScore=(int)$period['ca_count']+(int)$period['exam_count'];
    $expectedStmt=$mysqli->prepare("SELECT (SELECT COUNT(*) FROM users WHERE role='student' AND class_id=?) * (SELECT COUNT(DISTINCT subject_id) FROM teacher_allocations WHERE class_id=?) AS expected_rows");
    $expectedStmt->bind_param('ii',$period['class_id'],$period['class_id']);$expectedStmt->execute();
    $expectedRows=(int)$expectedStmt->get_result()->fetch_assoc()['expected_rows'];$expectedStmt->close();
    $filledComponents=array_sum(array_column($scores,'filled'));
    $expectedComponents=$expectedRows*$requiredPerScore;
    $progressPercent=$expectedComponents?min(100,round($filledComponents/$expectedComponents*100)):0;
} catch(Throwable $e){$error=$e->getMessage();}
include '../includes/header.php';
?>
<style>
.term-review{--review-ink:#1d2b34;--review-muted:#6c7a83;--review-line:#e3e9ec;--review-teal:#137c72;color:var(--review-ink)}
.term-review .review-heading{padding:1.5rem 1.6rem;background:#f5faf9;border:1px solid #dcebe8;border-radius:10px}
.term-review .review-kicker{font-size:.72rem;font-weight:700;color:var(--review-teal);text-transform:uppercase;letter-spacing:.08em}
.term-review h1{font-size:1.65rem;font-weight:700;margin:.35rem 0}
.term-review .review-meta{color:var(--review-muted);margin:0}
.term-review .review-status{border-radius:20px;padding:.45rem .8rem;font-weight:700;text-transform:capitalize}
.term-review .review-status.draft{background:#fff2d8;color:#805a12}
.term-review .review-status.submitted{background:#e5f2ff;color:#235c91}
.term-review .review-status.approved{background:#e4f4ee;color:#20654d}
.term-review .review-status.recalled{background:#edf0f2;color:#56636b}
.term-review .review-progress{background:#fff;border:1px solid var(--review-line);border-radius:10px;padding:1rem 1.2rem}
.term-review .progress{height:8px;background:#e8efee}
.term-review .progress-bar{background:var(--review-teal)}
.term-review .review-table{border:1px solid var(--review-line);border-radius:10px;overflow:hidden;background:#fff}
.term-review .review-table thead th{font-size:.76rem;text-transform:uppercase;color:#596972;background:#f4f7f8;border-bottom:1px solid var(--review-line);white-space:nowrap}
.term-review .review-table td{vertical-align:top;min-width:105px}
.term-review .score-list{font-size:.88rem;line-height:1.7;min-width:155px}
.term-review .release-action{min-width:118px}
.term-review .release-badge{display:inline-block;min-width:94px;text-align:center;border-radius:20px;padding:.35rem .65rem;font-size:.78rem;font-weight:700}
.term-review .release-badge.approved{background:#e4f4ee;color:#20654d}
.term-review .release-badge.submitted{background:#e5f2ff;color:#235c91}
.term-review .release-badge.draft{background:#fff2d8;color:#805a12}
.term-review .release-badge.recalled{background:#edf0f2;color:#56636b}
@media(max-width:767px){.term-review .review-heading{padding:1rem}.term-review h1{font-size:1.35rem}.term-review .review-actions{position:static}}
</style>
<main class="container-fluid py-4 term-review">
    <header class="review-heading d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div><div class="review-kicker">Academic results / Review</div><h1><?php echo $period?htmlspecialchars($period['class_name'].' · '.termLabel((int)$period['term'])):'Review term result';?></h1><p class="review-meta"><?php echo $period?htmlspecialchars($period['session_name'].' session · '.count($scores).' student-subject rows'):'';?></p></div>
        <div class="d-flex align-items-center gap-2"><a class="btn btn-outline-secondary" href="term_results.php<?php echo $period?'?session_id='.(int)$period['session_id']:'';?>"><i class="fas fa-arrow-left me-1"></i> Results overview</a><?php if($period):?><span class="review-status submitted">Individual release</span><?php endif;?></div>
    </header>
    <?php if($message):?><div class="alert alert-info"><?php echo htmlspecialchars($message);?></div><?php endif;?>
    <?php if($error):?><div class="alert alert-warning"><?php echo htmlspecialchars($error);?></div><?php else:?>
        <section class="review-progress mb-3" aria-label="Score completion">
            <div class="d-flex justify-content-between align-items-center gap-3 mb-2"><div><strong>Score completion</strong><div class="small text-muted">A result can be released when all configured components for that student and subject are entered.</div></div><strong><?php echo number_format($filledComponents); ?> / <?php echo number_format($expectedComponents); ?></strong></div>
            <div class="progress" role="progressbar" aria-valuenow="<?php echo $progressPercent;?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:<?php echo $progressPercent;?>%"></div></div>
            <div class="small text-muted mt-2"><?php echo (int)$releaseCounts['approved'];?> released · <?php echo (int)$releaseCounts['submitted'];?> awaiting release · <?php echo (int)$releaseCounts['draft'];?> draft · <?php echo (int)$releaseCounts['recalled'];?> recalled</div>
        </section>
        <?php if(!$scores):?><div class="alert alert-info">No scores have been entered yet.</div><?php else:?>
            <section class="review-table"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Student</th><th>Subject</th><th>CA components</th><th>Exam components</th><th>Entered</th><th>Total</th><th>Percent</th><th>Release</th><th>Action</th></tr></thead><tbody>
            <?php foreach($scores as $score):ksort($score['ca']);ksort($score['exam']);$maximum=$score['maximum'];$percent=$maximum?($score['total']/$maximum*100):0;$formatValues=static function(array $values):string{$parts=[];foreach($values as $item){$value=$item['score'];$label=htmlspecialchars($item['label'],ENT_QUOTES,'UTF-8');$parts[]=$label.': '.($value===null?'-':rtrim(rtrim(number_format($value,2,'.',''),'0'),'.')).' / '.rtrim(rtrim(number_format($item['maximum'],2,'.',''),'0'),'.');}return implode('<br>',$parts);};$isComplete=(int)$score['filled']===$requiredPerScore;$releaseStatus=$score['release_status'];?><tr><td class="fw-semibold"><?php echo htmlspecialchars($score['student']);?></td><td><?php echo htmlspecialchars($score['subject']);?></td><td class="score-list"><?php echo $formatValues($score['ca']);?></td><td class="score-list"><?php echo $formatValues($score['exam']);?></td><td><?php echo (int)$score['filled'];?> / <?php echo $requiredPerScore;?></td><td class="fw-semibold"><?php echo number_format($score['total'],2).' / '.number_format($maximum,2);?></td><td><?php echo number_format($percent,1);?>%</td><td><span class="release-badge <?php echo htmlspecialchars($releaseStatus);?>"><?php echo htmlspecialchars($releaseStatus==='approved'?'Released':($releaseStatus==='submitted'?'Awaiting release':ucfirst($releaseStatus)));?></span></td><td class="text-nowrap">
                <?php if($releaseStatus==='approved'):?><form method="post" action="term_results.php?session_id=<?php echo (int)$period['session_id'];?>" onsubmit="return confirm('Recall this result for this student only?');"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(termResultsCsrfToken());?>"><input type="hidden" name="action" value="score_status"><input type="hidden" name="period_id" value="<?php echo (int)$period['id'];?>"><input type="hidden" name="score_id" value="<?php echo $score['id'];?>"><input type="hidden" name="next_status" value="recalled"><input type="hidden" name="return_to" value="review"><button class="btn btn-sm btn-outline-danger release-action"><i class="fas fa-undo me-1"></i> Recall</button></form>
                <?php elseif($isComplete):?><form method="post" action="term_results.php?session_id=<?php echo (int)$period['session_id'];?>"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(termResultsCsrfToken());?>"><input type="hidden" name="action" value="score_status"><input type="hidden" name="period_id" value="<?php echo (int)$period['id'];?>"><input type="hidden" name="score_id" value="<?php echo $score['id'];?>"><input type="hidden" name="next_status" value="approved"><input type="hidden" name="return_to" value="review"><button class="btn btn-sm btn-success release-action"><i class="fas fa-check me-1"></i> Release</button></form><?php else:?><button class="btn btn-sm btn-outline-secondary release-action" disabled title="Complete every configured score first">Incomplete</button><?php endif;?>
                </td></tr><?php endforeach;?>
            </tbody></table></div></section>
        <?php endif;?>
        <?php if($period):?><p class="small text-muted mt-3 mb-0">Release and recall actions apply only to the student-subject row they appear beside.</p><?php endif;?>
    <?php endif;?>
</main>
<?php include '../includes/footer.php'; ?>
