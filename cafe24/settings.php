<?php
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-3xl mx-auto space-y-8">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">학생 프로필 및 데이터 관리</h1>
        <p class="text-xs text-slate-500 mt-1">
            학생별로 진단 기록과 맞춤 훈련지가 완전히 분리되어 관리됩니다.
        </p>
    </div>

    <!-- 현재 활성 학생 정보 수정 -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 sm:p-8 space-y-6 shadow-xs">
        <div class="border-b border-slate-100 pb-4">
            <h2 class="text-lg font-bold text-slate-900">현재 활성 학생 정보</h2>
            <p class="text-xs text-slate-500 mt-0.5">선택된 학생의 이름과 학년을 변경합니다.</p>
        </div>

        <form id="edit-student-form" onsubmit="handleUpdateStudent(event)" class="space-y-4">
            <input type="hidden" name="id" value="<?php echo $active_student['id']; ?>">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">학생 이름</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($active_student['name']); ?>" required
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">학년</label>
                    <select name="grade" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                        <option value="4" <?php echo $active_student['grade'] == 4 ? 'selected' : ''; ?>>초등학교 4학년</option>
                        <option value="5" <?php echo $active_student['grade'] == 5 ? 'selected' : ''; ?>>초등학교 5학년 (표준)</option>
                        <option value="6" <?php echo $active_student['grade'] == 6 ? 'selected' : ''; ?>>초등학교 6학년</option>
                    </select>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="py-2.5 px-5 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-xl text-sm transition">
                    정보 저장하기
                </button>
            </div>
        </form>
    </div>

    <!-- 새 학생 등록 -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 sm:p-8 space-y-6 shadow-xs">
        <div class="border-b border-slate-100 pb-4">
            <h2 class="text-lg font-bold text-slate-900">새 학생 등록</h2>
            <p class="text-xs text-slate-500 mt-0.5">다자녀 또는 학원생을 추가하여 개별 관리할 수 있습니다.</p>
        </div>

        <form id="create-student-form" onsubmit="handleCreateStudent(event)" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">신규 학생 이름</label>
                    <input type="text" name="name" placeholder="예: 김민준, 학생2" required
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">학년</label>
                    <select name="grade" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-slate-900">
                        <option value="4">초등학교 4학년</option>
                        <option value="5" selected>초등학교 5학년</option>
                        <option value="6">초등학교 6학년</option>
                    </select>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="py-2.5 px-5 bg-slate-800 hover:bg-slate-700 text-white font-semibold rounded-xl text-sm transition">
                    새 학생 추가하기
                </button>
            </div>
        </form>
    </div>

    <!-- 전체 등록 학생 목록 & 개별 초기화/삭제 -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 sm:p-8 space-y-4 shadow-xs">
        <div class="border-b border-slate-100 pb-4">
            <h2 class="text-lg font-bold text-slate-900">등록된 학생 목록 (총 <?php echo count($all_students); ?>명)</h2>
            <p class="text-xs text-slate-500 mt-0.5">학생을 클릭하여 전환하거나, 해당 학생의 진단 데이터만 격리 초기화할 수 있습니다.</p>
        </div>

        <div class="divide-y divide-slate-100">
            <?php foreach ($all_students as $std): ?>
                <div class="py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full <?php echo $std['id'] === $active_student['id'] ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'; ?> flex items-center justify-center font-bold text-sm">
                            <?php echo mb_substr($std['name'], 0, 1, 'UTF-8'); ?>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-900 text-sm"><?php echo htmlspecialchars($std['name']); ?></span>
                                <span class="text-xs px-2 py-0.5 rounded bg-slate-100 text-slate-600 font-medium">초<?php echo $std['grade']; ?></span>
                                <?php if ($std['id'] === $active_student['id']): ?>
                                    <span class="text-[11px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                                        현재 선택됨
                                    </span>
                                <?php endif; ?>
                            </div>
                            <span class="text-[11px] text-slate-400">등록일: <?php echo date('Y.m.d', strtotime($std['created_at'])); ?></span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <?php if ($std['id'] !== $active_student['id']): ?>
                            <button onclick="switchStudent('<?php echo $std['id']; ?>')" class="py-1.5 px-3 rounded-lg text-xs font-semibold bg-slate-100 hover:bg-slate-200 text-slate-800 transition">
                                이 학생으로 전환
                            </button>
                        <?php endif; ?>

                        <button onclick="handleResetStudent('<?php echo $std['id']; ?>', '<?php echo htmlspecialchars($std['name']); ?>')" class="py-1.5 px-3 rounded-lg text-xs font-semibold bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 transition">
                            기록 초기화
                        </button>

                        <?php if (count($all_students) > 1): ?>
                            <button onclick="handleDeleteStudent('<?php echo $std['id']; ?>', '<?php echo htmlspecialchars($std['name']); ?>')" class="py-1.5 px-3 rounded-lg text-xs font-semibold bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200 transition">
                                삭제
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
function handleUpdateStudent(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    formData.append('action', 'update');

    fetch('api/student_action.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('학생 정보가 수정되었습니다.');
            window.location.reload();
        } else {
            alert(data.error || '수정 실패');
        }
    })
    .catch(err => alert('오류가 발생했습니다.'));
}

function handleCreateStudent(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    formData.append('action', 'create');

    fetch('api/student_action.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('새 학생이 등록되었습니다.');
            window.location.reload();
        } else {
            alert(data.error || '등록 실패');
        }
    })
    .catch(err => alert('오류가 발생했습니다.'));
}

function handleResetStudent(studentId, studentName) {
    if (!confirm(`'${studentName}' 학생의 모든 진단 및 연습 기록을 초기화하시겠습니까?\n(다른 학생의 기록은 안전하게 보존됩니다)`)) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'reset');
    formData.append('student_id', studentId);

    fetch('api/student_action.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(`'${studentName}' 학생의 데이터가 초기화되었습니다.`);
            window.location.reload();
        } else {
            alert(data.error || '초기화 실패');
        }
    })
    .catch(err => alert('오류가 발생했습니다.'));
}

function handleDeleteStudent(studentId, studentName) {
    if (!confirm(`'${studentName}' 학생을 정말 삭제하시겠습니까?\n관련된 모든 진단 기록도 함께 삭제됩니다.`)) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('student_id', studentId);

    fetch('api/student_action.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(`'${studentName}' 학생이 삭제되었습니다.`);
            window.location.reload();
        } else {
            alert(data.error || '삭제 실패');
        }
    })
    .catch(err => alert('오류가 발생했습니다.'));
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
