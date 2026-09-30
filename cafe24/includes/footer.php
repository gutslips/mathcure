    </main>

    <footer class="no-print bg-white border-t border-slate-200 mt-12 py-6 text-center text-xs text-slate-500">
        <div class="max-w-7xl mx-auto px-4">
            <p>초5 연산 트레이너 &copy; <?php echo date('Y'); ?> MathCure. All rights reserved.</p>
            <p class="mt-1 text-[11px] text-slate-400">카페24 웹호스팅 (PHP + MySQL) 최적화 버전</p>
        </div>
    </footer>

    <script>
    function toggleStudentMenu() {
        const menu = document.getElementById('student-dropdown-menu');
        menu.classList.toggle('hidden');
    }

    document.addEventListener('click', function(e) {
        const btn = document.getElementById('student-dropdown-btn');
        const menu = document.getElementById('student-dropdown-menu');
        if (btn && menu && !btn.contains(e.target) && !menu.contains(e.target)) {
            menu.classList.add('hidden');
        }
    });

    function switchStudent(studentId) {
        const formData = new FormData();
        formData.append('action', 'switch');
        formData.append('student_id', studentId);

        fetch('api/student_action.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.error || '학생 전환 실패');
            }
        })
        .catch(err => {
            console.error(err);
            window.location.reload();
        });
    }
    </script>
</body>
</html>
