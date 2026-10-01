    </main>

    <footer class="no-print bg-white border-t border-slate-200 mt-12 py-6 text-center text-xs text-slate-500 mb-16 md:mb-0">
        <div class="max-w-7xl mx-auto px-4">
            <p>초5 연산 트레이너 &copy; <?php echo date('Y'); ?> MathCure. All rights reserved.</p>
        </div>
    </footer>

    <script>
    // 학생 전환 메뉴 토글
    function toggleStudentMenu() {
        const menu = document.getElementById('student-dropdown-menu');
        if (menu) menu.classList.toggle('hidden');
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

    // PWA Service Worker 등록
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('sw.js')
                .then(reg => console.log('ServiceWorker registered'))
                .catch(err => console.log('ServiceWorker failed:', err));
        });
    }

    // PWA 앱 설치 배너 처리
    let deferredPrompt;
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        const banner = document.getElementById('pwa-install-banner');
        if (banner && !sessionStorage.getItem('pwa_dismissed')) {
            banner.classList.remove('hidden');
        }
    });

    const installBtn = document.getElementById('pwa-install-btn');
    if (installBtn) {
        installBtn.addEventListener('click', async () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const choice = await deferredPrompt.userChoice;
                if (choice.outcome === 'accepted') {
                    dismissPwaBanner();
                }
                deferredPrompt = null;
            }
        });
    }

    function dismissPwaBanner() {
        const banner = document.getElementById('pwa-install-banner');
        if (banner) banner.classList.add('hidden');
        sessionStorage.setItem('pwa_dismissed', '1');
    }
    </script>
</body>
</html>
