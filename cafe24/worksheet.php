<?php
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/lib/generators.php';

$type = $_GET['type'] ?? 'divide5';
$difficulty = (int)($_GET['difficulty'] ?? 2);
$count = (int)($_GET['count'] ?? 20);
$seed = $_GET['seed'] ?? ('ws_' . substr(md5(uniqid(mt_rand(), true)), 0, 8));

// 워크시트 문제 생성 (60% 취약 / 20% 기초 / 20% 복습)
$problems = generate_worksheet_problems($type, $count, $difficulty, $seed);

// DB에 저장 (옵션)
try {
    $wsId = 'ws_' . bin2hex(random_bytes(8));
    $t_ws = table('worksheets');
    $stmt = $pdo->prepare("
        INSERT INTO `{$t_ws}` 
        (`id`, `student_id`, `title`, `subject`, `grade`, `difficulty`, `problem_type`, `count`, `seed`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $title = (PROBLEM_TYPE_LABELS[$type] ?? $type) . " 맞춤 훈련지";
    $stmt->execute([
        $wsId,
        $active_student['id'],
        $title,
        '나눗셈',
        $active_student['grade'],
        $difficulty,
        $type,
        $count,
        $seed
    ]);
} catch (Exception $e) {
    // DB 저장 실패해도 화면 출력은 유지
}
?>

<!-- 상단 인쇄 및 옵션 바 (화면 전용, 인쇄 시 숨김) -->
<div class="no-print bg-white border border-slate-200 rounded-2xl p-6 mb-8 shadow-xs">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-slate-900">맞춤 훈련지 생성 및 A4 인쇄</h1>
            <p class="text-xs text-slate-500 mt-1">
                문제지와 정답지가 페이지별로 완벽히 분리되어 깔끔하게 인쇄됩니다.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <button onclick="window.print()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-bold hover:bg-slate-800 transition shadow-sm">
                <svg class="w-4 h-4 fill-white" viewBox="0 0 24 24"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
                A4 인쇄하기 (Ctrl+P)
            </button>
            <a href="practice.php?type=<?php echo $type; ?>" class="px-4 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-sm font-semibold hover:bg-slate-50 transition">
                온라인으로 풀기
            </a>
        </div>
    </div>

    <!-- 옵션 폼 -->
    <form method="GET" action="worksheet.php" class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-6 pt-6 border-t border-slate-100">
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">훈련 유형</label>
            <select name="type" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs font-medium">
                <?php foreach (PROBLEM_TYPE_LABELS as $k => $label): ?>
                    <option value="<?php echo $k; ?>" <?php echo $type === $k ? 'selected' : ''; ?>>
                        <?php echo $label; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">난이도</label>
            <select name="difficulty" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs font-medium">
                <option value="1" <?php echo $difficulty === 1 ? 'selected' : ''; ?>>Level 1 (기초)</option>
                <option value="2" <?php echo $difficulty === 2 ? 'selected' : ''; ?>>Level 2 (표준)</option>
                <option value="3" <?php echo $difficulty === 3 ? 'selected' : ''; ?>>Level 3 (심화)</option>
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">문항 수</label>
            <select name="count" class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs font-medium">
                <option value="20" <?php echo $count === 20 ? 'selected' : ''; ?>>20문제 (권장)</option>
                <option value="30" <?php echo $count === 30 ? 'selected' : ''; ?>>30문제</option>
            </select>
        </div>

        <div class="flex items-end gap-2">
            <input type="hidden" name="seed" value="<?php echo htmlspecialchars($seed); ?>">
            <button type="submit" class="w-full py-2 px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs rounded-lg transition">
                설정 적용
            </button>
            <a href="worksheet.php?type=<?php echo $type; ?>" class="py-2 px-3 bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-600 font-semibold text-xs rounded-lg whitespace-nowrap transition" title="새로운 문제 생성">
                새로고침
            </a>
        </div>
    </form>
</div>

<!-- ===================== [A4 인쇄 영역 1: 문제지] ===================== -->
<div class="print-page bg-white p-6 sm:p-10 border border-slate-200 rounded-2xl shadow-sm mb-8 print:border-none print:shadow-none print:p-0">
    <!-- 문제지 헤더 -->
    <div class="border-b-2 border-slate-900 pb-4 mb-6">
        <div class="flex justify-between items-start">
            <div>
                <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">초등 5학년 연산 역량 강화 훈련</span>
                <h2 class="text-2xl font-black text-slate-900 mt-1">
                    <?php echo PROBLEM_TYPE_LABELS[$type] ?? $type; ?> 맞춤 연산 문제지
                </h2>
                <div class="text-xs text-slate-500 mt-1">
                    난이도: Level <?php echo $difficulty; ?> · 시드: <code class="font-mono text-[10px]"><?php echo htmlspecialchars($seed); ?></code>
                </div>
            </div>

            <!-- 학생 기입란 -->
            <table class="text-xs border-collapse border border-slate-400">
                <tr>
                    <td class="border border-slate-300 bg-slate-50 px-3 py-1 font-semibold text-center">날짜</td>
                    <td class="border border-slate-300 px-4 py-1 w-24">. &nbsp; . &nbsp; .</td>
                    <td class="border border-slate-300 bg-slate-50 px-3 py-1 font-semibold text-center">이름</td>
                    <td class="border border-slate-300 px-4 py-1 font-bold text-center w-28"><?php echo htmlspecialchars($active_student['name']); ?></td>
                </tr>
                <tr>
                    <td class="border border-slate-300 bg-slate-50 px-3 py-1 font-semibold text-center">시간</td>
                    <td class="border border-slate-300 px-4 py-1 text-center">&nbsp; 분 &nbsp; 초</td>
                    <td class="border border-slate-300 bg-slate-50 px-3 py-1 font-semibold text-center">점수</td>
                    <td class="border border-slate-300 px-4 py-1 text-center font-bold">/ <?php echo count($problems); ?></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- 2열 그리드 문제 레이아웃 -->
    <div class="grid grid-cols-2 gap-x-10 gap-y-6">
        <?php foreach ($problems as $idx => $p): ?>
            <div class="flex items-start gap-3 p-3.5 rounded-xl border border-slate-200 bg-white">
                <span class="font-bold text-slate-400 text-sm w-6 shrink-0"><?php echo ($idx + 1); ?>.</span>
                <div class="flex-1">
                    <div class="text-lg font-mono font-bold text-slate-900 tracking-wide">
                        <?php echo $p['question']; ?> <span class="inline-block border-b-2 border-slate-400 w-16 text-center"></span>
                    </div>
                    <!-- 계산 풀이 여백 -->
                    <div class="h-12 mt-2 border border-dashed border-slate-200 rounded bg-slate-50/50 print:bg-white"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ===================== [A4 인쇄 영역 2: 정답지] ===================== -->
<div class="print-page bg-white p-6 sm:p-10 border border-slate-200 rounded-2xl shadow-sm print:border-none print:shadow-none print:p-0">
    <div class="border-b-2 border-slate-900 pb-4 mb-6">
        <div class="flex justify-between items-center">
            <div>
                <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">정답 및 채점표</span>
                <h2 class="text-2xl font-black text-slate-900 mt-1">
                    <?php echo PROBLEM_TYPE_LABELS[$type] ?? $type; ?> 빠른 정답표
                </h2>
            </div>
            <div class="text-xs text-slate-500">
                학생명: <strong><?php echo htmlspecialchars($active_student['name']); ?></strong> (초<?php echo $active_student['grade']; ?>)
            </div>
        </div>
    </div>

    <!-- 정답 표 (4열 그리드 컴팩트) -->
    <div class="grid grid-cols-4 gap-3">
        <?php foreach ($problems as $idx => $p): ?>
            <div class="flex items-center justify-between p-2.5 rounded-lg border border-slate-200 bg-slate-50">
                <span class="text-xs font-bold text-slate-500"><?php echo ($idx + 1); ?>번</span>
                <span class="text-base font-mono font-black text-slate-900"><?php echo number_format($p['answer']); ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- 나눗셈 핵심 전략 팁 박스 -->
    <div class="mt-8 p-5 bg-slate-50 border border-slate-200 rounded-xl space-y-3">
        <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider">선생님 / 학부모 지도 가이드</h3>
        <ul class="text-xs text-slate-600 space-y-1.5 list-disc list-inside leading-relaxed">
            <li><strong>÷5 전략:</strong> 피제수에 먼저 2를 곱한 후 10으로 나누면(끝자리 0 제거) 세로셈 없이 암산이 가능합니다.</li>
            <li><strong>÷4 전략:</strong> 2로 절반을 나눈 뒤, 그 결과를 다시 2로 나누면 실수를 대폭 줄일 수 있습니다.</li>
            <li><strong>큰 수 처리:</strong> 끝자리 0을 잠시 떼어두고 앞자리 기본 나눗셈을 한 뒤 0을 그대로 붙여줍니다.</li>
        </ul>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
