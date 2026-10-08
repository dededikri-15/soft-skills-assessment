(function () {
    'use strict';

    const timerEl      = document.getElementById('timer');
    const progressText = document.getElementById('progressText');
    const progressPct  = document.getElementById('progressPercent');
    const progressBar  = document.getElementById('progressBar');
    const optionList   = document.getElementById('optionList');
    const questionType = document.getElementById('questionType');
    const questionNum  = document.getElementById('questionNumber');
    const questionText = document.getElementById('questionText');
    const questionHint = document.getElementById('questionHint');
    const prevBtn      = document.getElementById('prevBtn');
    const nextBtn      = document.getElementById('nextBtn');
    const finishBtn    = document.getElementById('finishBtn');
    const questionNav  = document.getElementById('questionNav');

    const PETUNJUK_TIPE = {
        multiple_choice: 'Pilih satu jawaban yang paling sesuai.',
        checkbox: 'Pilih lebih dari satu jawaban bila dianggap tepat.',
        dropdown: 'Pilih satu jawaban dari daftar berikut.',
        scale: 'Pilih satu pernyataan yang paling menggambarkan dirimu.'
    };

    window.ExamConfig = window.ExamConfig || {
        totalQuestions: 60,
        durationMinutes: 30,
        serverEndTime: null,
        questions: [],
        existingAnswers: {}
    };

    let currentQuestion = 0;
    let answers = [];
    let timerInterval = null;
    let secondsLeft = null;
    let submitting = false;
    let pendingSaves = [];
    let pendingPayloads = {};

    function initTimer() {
        const cfg = window.ExamConfig;

        if (typeof cfg.remainingSeconds === 'number' && !isNaN(cfg.remainingSeconds)) {
            secondsLeft = Math.max(0, Math.floor(cfg.remainingSeconds));
        } else if (cfg.serverEndTime) {
            const end = new Date(String(cfg.serverEndTime).replace(' ', 'T')).getTime();
            secondsLeft = Math.max(0, Math.floor((end - Date.now()) / 1000));
        } else {
            return;
        }

        renderTimer(secondsLeft);
        timerInterval = setInterval(function () {
            secondsLeft = Math.max(0, secondsLeft - 1);
            renderTimer(secondsLeft);
            if (secondsLeft <= 0) {
                clearInterval(timerInterval);
                timerInterval = null;
                submitExam();
            }
        }, 1000);
    }

    function renderTimer(seconds) {
        if (!timerEl) return;
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        timerEl.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        timerEl.classList.toggle('urgent', seconds <= 300);
    }

    function flushSaves() {
        if (!pendingSaves.length) return Promise.resolve();
        return Promise.all(pendingSaves.slice());
    }

    function submitExam() {
        if (submitting) return;
        submitting = true;
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
        if (finishBtn) finishBtn.disabled = true;
        if (nextBtn) nextBtn.disabled = true;

        Promise.race([
            flushSaves(),
            new Promise(function (res) { setTimeout(res, 3000); })
        ]).then(autoSubmit, autoSubmit);
    }

    function autoSubmit() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'submit.php';
        document.body.appendChild(form);
        form.submit();
    }

    function updateProgress() {
        const total = window.ExamConfig.totalQuestions;
        const number = currentQuestion + 1;
        const percent = Math.round((number / total) * 100);

        if (progressText) progressText.textContent = 'Pertanyaan ' + number + ' dari ' + total;
        if (progressPct)  progressPct.textContent  = percent + '%';
        if (progressBar)  progressBar.style.width  = percent + '%';
    }

    function renderQuestion() {
        const q = window.ExamConfig.questions[currentQuestion];
        if (!q) return;

        if (questionType) questionType.textContent = (q.kategori || '').toUpperCase();
        if (questionNum)  questionNum.textContent  = 'Pertanyaan ' + (currentQuestion + 1);
        if (questionText) questionText.textContent = q.teks;
        if (questionHint) {
            const petunjuk = PETUNJUK_TIPE[q.tipe] || '';
            questionHint.textContent = petunjuk;
            questionHint.classList.toggle('hidden', !petunjuk);
        }

        if (optionList) {
            optionList.innerHTML = '';
            if (q.tipe === 'dropdown') {
                renderDropdown(q);
            } else {
                renderOptionList(q);
            }
        }

        if (prevBtn) prevBtn.style.visibility = currentQuestion === 0 ? 'hidden' : 'visible';

        // Soal terakhir: tombol "Berikutnya" disembunyikan.
        // Tombol "Selesaikan Tes" selalu tampil agar peserta boleh
        // mengumpulkan lebih awal (misal baru mengerjakan 5 soal).
        const isLast = currentQuestion === window.ExamConfig.questions.length - 1;
        if (nextBtn) nextBtn.classList.toggle('hidden', isLast);

        updateProgress();
        updateNav();
    }

    /* Kartu pilihan (format seperti gambar rancangan):
       - multiple_choice & scale : lingkaran (satu jawaban)
       - checkbox                 : kotak centang (boleh lebih dari satu)
    */
    function renderOptionList(q) {
        const multi = q.tipe === 'checkbox';
        const skala = q.tipe === 'scale';

        q.pilihan.forEach(function (p, idx) {
            const div = document.createElement('div');
            div.className = 'option'
                + (multi ? ' checkbox-option' : '')
                + (skala ? ' scale-choice' : '');
            div.setAttribute('role', multi ? 'checkbox' : 'radio');
            div.setAttribute('tabindex', '0');
            div.setAttribute('aria-checked', isSelected(q, idx) ? 'true' : 'false');
            if (isSelected(q, idx)) div.classList.add('selected');
            div.innerHTML = '<div class="option-circle"></div>'
                + '<div class="option-label">' + escapeHtml(p.teks) + '</div>';
            div.onclick = function () { selectAnswer(idx); };
            div.onkeydown = function (e) {
                if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
                    e.preventDefault();
                    selectAnswer(idx);
                }
            };
            optionList.appendChild(div);
        });
    }

    /* Dropdown/select untuk pilihan yang teksnya panjang */
    function renderDropdown(q) {
        const wrap = document.createElement('div');
        wrap.className = 'select-wrap';

        const sel = document.createElement('select');
        sel.className = 'question-select';
        sel.setAttribute('aria-label', 'Pilih jawaban');

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = '-- Pilih jawaban --';
        placeholder.disabled = true;
        placeholder.selected = true;
        sel.appendChild(placeholder);

        q.pilihan.forEach(function (p, idx) {
            const opt = document.createElement('option');
            opt.value = String(idx);
            opt.textContent = p.teks;
            sel.appendChild(opt);
        });

        const ans = answers[currentQuestion];
        if (typeof ans === 'number' && q.pilihan[ans]) {
            sel.value = String(ans);
            wrap.classList.add('answered');
        }

        sel.onchange = function () {
            if (sel.value === '') {
                wrap.classList.remove('answered');
                return;
            }
            wrap.classList.add('answered');
            selectAnswer(parseInt(sel.value, 10), true);
        };

        wrap.appendChild(sel);
        optionList.appendChild(wrap);
    }

    function isSelected(q, idx) {
        const ans = answers[currentQuestion];
        if (ans === undefined || ans === null) return false;
        if (q.tipe === 'checkbox') return Array.isArray(ans) && ans.indexOf(idx) >= 0;
        return ans === idx;
    }

    function isAnswered(value) {
        if (value === undefined || value === null) return false;
        if (Array.isArray(value)) return value.length > 0;
        return true;
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function selectAnswer(idx, skipRender) {
        const q = window.ExamConfig.questions[currentQuestion];
        if (!q || !q.pilihan[idx]) return;

        if (q.tipe === 'checkbox') {
            const picked = Array.isArray(answers[currentQuestion]) ? answers[currentQuestion].slice() : [];
            const pos = picked.indexOf(idx);
            if (pos >= 0) {
                picked.splice(pos, 1);
            } else {
                picked.push(idx);
            }
            picked.sort(function (a, b) { return a - b; });
            answers[currentQuestion] = picked;
            saveSelection(q, picked);
        } else {
            answers[currentQuestion] = idx;
            saveSelection(q, [idx]);
        }

        if (skipRender) updateNav();
        else renderQuestion();
    }

    function saveSelection(q, optionIndexes) {
        const ids = optionIndexes
            .map(function (i) { return q.pilihan[i] ? q.pilihan[i].id : 0; })
            .filter(function (id) { return id > 0; });

        return saveAnswer(q.id, ids);
    }

    function updateNav() {
        if (!questionNav) return;
        questionNav.innerHTML = '';
        window.ExamConfig.questions.forEach(function (q, idx) {
            const terjawab = isAnswered(answers[idx]);
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'nav-btn'
                + (idx === currentQuestion ? ' active' : '')
                + (terjawab ? ' answered' : '');
            btn.textContent = idx + 1;
            btn.title = 'Soal ' + (idx + 1) + (terjawab ? ' — terjawab' : ' — belum dijawab');
            btn.setAttribute('aria-label', btn.title);
            if (idx === currentQuestion) btn.setAttribute('aria-current', 'true');
            btn.onclick = function () { goToQuestion(idx); };
            questionNav.appendChild(btn);
        });
    }

    function scrollKeKartu() {
        const card = document.querySelector('.question-card');
        if (!card) return;
        const target = Math.max(0, card.offsetTop - 84);
        if (Math.abs(window.scrollY - target) > 12) {
            window.scrollTo({ top: target, behavior: 'smooth' });
        }
    }

    function goToQuestion(idx) {
        currentQuestion = idx;
        renderQuestion();
        scrollKeKartu();
    }

    function saveAnswer(soalId, pilihanIds) {
        if (!Array.isArray(pilihanIds)) {
            pilihanIds = pilihanIds ? [pilihanIds] : [];
        }

        const payload = JSON.stringify({
            soal_id: soalId,
            pilihan_ids: pilihanIds,
            pilihan_id: pilihanIds[0] || 0
        });

        const request = fetch('proses_jawaban.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: payload
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.ok) console.warn('Gagal menyimpan jawaban:', data.message);
        })
        .catch(function (err) { console.error(err); })
        .then(function () {
            pendingSaves = pendingSaves.filter(function (p) { return p !== request; });
            delete pendingPayloads[soalId];
        });

        pendingSaves.push(request);
        pendingPayloads[soalId] = payload;
        return request;
    }

    window.addEventListener('beforeunload', function (e) {
        if (submitting) return;

        if (pendingSaves.length) {
            if (navigator.sendBeacon) {
                Object.keys(pendingPayloads).forEach(function (soalId) {
                    navigator.sendBeacon(
                        'proses_jawaban.php',
                        new Blob([pendingPayloads[soalId]], { type: 'application/json' })
                    );
                });
            }
            e.preventDefault();
            e.returnValue = '';
            return;
        }

        if (timerInterval) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        answers = new Array(window.ExamConfig.questions.length).fill(undefined);

        window.ExamConfig.questions.forEach(function (q, idx) {
            const stored = window.ExamConfig.existingAnswers
                ? window.ExamConfig.existingAnswers[q.id]
                : null;

            if (!stored || !stored.length) return;

            const indexes = [];
            stored.forEach(function (pilihanId) {
                const pIdx = q.pilihan.findIndex(function (p) { return p.id === pilihanId; });
                if (pIdx >= 0) indexes.push(pIdx);
            });

            if (!indexes.length) return;

            if (q.tipe === 'checkbox') {
                indexes.sort(function (a, b) { return a - b; });
                answers[idx] = indexes;
            } else {
                answers[idx] = indexes[0];
            }
        });

        initTimer();
        renderQuestion();

        if (prevBtn) prevBtn.onclick = function () { if (currentQuestion > 0) goToQuestion(currentQuestion - 1); };
        if (nextBtn) nextBtn.onclick = function () { if (currentQuestion < window.ExamConfig.questions.length - 1) goToQuestion(currentQuestion + 1); };
        if (finishBtn) finishBtn.onclick = function () {
            if (submitting) return;

            const total = window.ExamConfig.questions.length;
            let terjawab = 0;
            for (let i = 0; i < answers.length; i++) {
                if (isAnswered(answers[i])) terjawab++;
            }
            const sisa = total - terjawab;

            let pesan = 'Apa kamu yakin ingin menyelesaikan tes sekarang?';
            if (sisa > 0) {
                pesan += '\n\nBaru ' + terjawab + ' dari ' + total + ' soal yang terjawab '
                    + '(masih ada ' + sisa + ' soal kosong).';
            } else {
                pesan += '\n\nSemua ' + total + ' soal sudah terjawab.';
            }
            pesan += '\nJawaban yang sudah tersimpan akan tetap dikumpulkan.';

            if (confirm(pesan)) {
                submitExam();
            }
        };

        // Tema terang / gelap (disimpan di localStorage)
        const themeBtn = document.getElementById('themeToggle');
        function terapkanTema() {
            if (!themeBtn) return;
            const gelap = document.documentElement.classList.contains('dark');
            themeBtn.setAttribute('aria-pressed', gelap ? 'true' : 'false');
            const sun  = themeBtn.querySelector('.icon-sun');
            const moon = themeBtn.querySelector('.icon-moon');
            if (sun)  sun.classList.toggle('hidden', gelap);
            if (moon) moon.classList.toggle('hidden', !gelap);
        }
        terapkanTema();
        if (themeBtn) {
            themeBtn.onclick = function () {
                document.documentElement.classList.toggle('dark');
                try {
                    localStorage.setItem('theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
                } catch (e) {}
                terapkanTema();
            };
        }

        // Navigasi dengan tombol panah keyboard.
        document.addEventListener('keydown', function (e) {
            if (submitting) return;
            const tag = (e.target && e.target.tagName ? e.target.tagName : '').toLowerCase();
            if (tag === 'input' || tag === 'textarea' || tag === 'select') return;

            if (e.key === 'ArrowRight' && currentQuestion < window.ExamConfig.questions.length - 1) {
                goToQuestion(currentQuestion + 1);
            } else if (e.key === 'ArrowLeft' && currentQuestion > 0) {
                goToQuestion(currentQuestion - 1);
            }
        });
    });

    window.ExamApp = {
        saveAnswer: saveAnswer,
        setQuestion: function (i) { currentQuestion = i; updateProgress(); },
        stopTimer: function () { if (timerInterval) clearInterval(timerInterval); }
    };

})();
