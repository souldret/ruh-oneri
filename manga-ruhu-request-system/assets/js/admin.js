/* MangaRuhu Seri Öneri Sistemi — Admin JS */
/* global mrrsAdmin, ajaxurl */

(function () {
	'use strict';

	const cfg   = window.mrrsAdmin || {};
	const nonce = cfg.nonce || '';

	const page = document.getElementById('mrrs-requests-page');
	if (!page) return;

	const tbody      = document.getElementById('mrrs-tbody');
	const notice     = document.getElementById('mrrs-notice');
	const pagination = document.getElementById('mrrs-pagination');
	const filterStatus = document.getElementById('mrrs-filter-status');
	const filterSort   = document.getElementById('mrrs-filter-sort');
	const searchEl     = document.getElementById('mrrs-search');
	const checkAll     = document.getElementById('mrrs-check-all');
	const bulkAction   = document.getElementById('mrrs-bulk-action');
	const bulkApply    = document.getElementById('mrrs-bulk-apply');
	const modal        = document.getElementById('mrrs-modal');
	const modalSave    = document.getElementById('mrrs-modal-save');
	const modalClose   = document.getElementById('mrrs-modal-close');

	let state = { page: 1, perPage: 20, totalPages: 1, status: '', sort: 'newest', search: '' };

	/* ── Ajax yardımcı ── */
	function ajax(action, data) {
		const fd = new FormData();
		fd.append('action', action);
		fd.append('nonce', nonce);
		Object.entries(data || {}).forEach(([k, v]) => {
			if (Array.isArray(v)) {
				v.forEach(id => fd.append(k + '[]', id));
			} else {
				fd.append(k, v);
			}
		});
		return fetch(ajaxurl, { method: 'POST', body: fd }).then(r => r.json());
	}

	/* ── Bildiri ── */
	function showNotice(msg, type) {
		notice.textContent = msg;
		notice.className = type === 'error' ? 'is-error' : 'is-success';
		notice.style.display = 'block';
		setTimeout(() => { notice.style.display = 'none'; }, 4000);
	}

	/* ── Durum rozeti ── */
	function statusBadge(status) {
		const labels = {
			pending:     'Beklemede',
			reviewing:   'İnceleniyor',
			approved:    'Onaylandı',
			rejected:    'Reddedildi',
			translating: 'Çeviriye Alındı',
		};
		const label = labels[status] || status;
		return `<span class="mrrs-status-badge mrrs-status-badge--${status}">${escHtml(label)}</span>`;
	}

	/* ── Listeyi yükle ── */
	function loadList() {
		if (tbody) tbody.innerHTML = '<tr><td colspan="6">Yükleniyor…</td></tr>';
		ajax('mrrs_admin_list_requests', {
			search:   state.search,
			status:   state.status,
			sort:     state.sort,
			mrrs_page: state.page,
			per_page: state.perPage,
		}).then(res => {
			if (!res.success) { showNotice(res.data?.message || 'Hata.', 'error'); return; }
			const { items, total, total_pages } = res.data;
			state.totalPages = total_pages || 1;

			if (!items || !items.length) {
				tbody.innerHTML = '<tr><td colspan="6">Öneri bulunamadı.</td></tr>';
				if (pagination) pagination.innerHTML = '';
				return;
			}

			tbody.innerHTML = items.map(item => `
				<tr data-id="${item.id}">
					<td><input type="checkbox" class="mrrs-cb" value="${item.id}"></td>
					<td>
						<strong>${escHtml(item.title)}</strong>
						${item.description ? `<p style="margin:2px 0 0;color:#666;font-size:.85em">${escHtml(item.description.substring(0,100))}${item.description.length>100?'…':''}</p>` : ''}
						${item.source_link ? `<p style="margin:2px 0 0;font-size:.8em"><a href="${escHtml(item.source_link)}" target="_blank" rel="noopener">Kaynak ↗</a></p>` : ''}
						<div style="margin-top:4px">
							<button class="button button-small mrrs-edit-btn" data-id="${item.id}">Düzenle</button>
							<button class="button button-small mrrs-delete-btn" data-id="${item.id}" style="color:#a00">Sil</button>
						</div>
					</td>
					<td>${(item.up_votes||0) + (item.down_votes||0)}</td>
					<td>${statusBadge(item.status)}</td>
					<td style="font-size:.85em;color:#666">${formatDate(item.created_at)}</td>
					<td>
						${item.status !== 'approved' ? `<button class="button button-small mrrs-quick-approve" data-id="${item.id}">Onayla</button>` : ''}
						${item.status !== 'rejected' ? `<button class="button button-small mrrs-quick-reject" data-id="${item.id}" style="color:#a00">Reddet</button>` : ''}
					</td>
				</tr>`).join('');

			renderPagination(state.page, state.totalPages);
		});
	}

	/* ── Sayfalama (ellipsis destekli) ── */
	function renderPagination(current, total) {
		if (!pagination) return;
		if (total <= 1) { pagination.innerHTML = ''; return; }
		const pages = buildPageNums(current, total);
		let html = '';
		const prevDis = current <= 1 ? ' disabled' : '';
		html += `<button class="button mrrs-admin-pg-btn"${prevDis} data-page="${current - 1}">&#9664;</button>`;
		pages.forEach(p => {
			if (p === '...') {
				html += '<span class="mrrs-admin-pg-ellipsis">&hellip;</span>';
			} else {
				const active = p === current ? ' button-primary' : '';
				html += `<button class="button${active} mrrs-admin-pg-btn" data-page="${p}">${p}</button>`;
			}
		});
		const nextDis = current >= total ? ' disabled' : '';
		html += `<button class="button mrrs-admin-pg-btn"${nextDis} data-page="${current + 1}">&#9654;</button>`;
		pagination.innerHTML = html;
	}

	function buildPageNums(cur, total) {
		if (total <= 7) {
			return Array.from({length: total}, (_, i) => i + 1);
		}
		const p = [1];
		if (cur > 3) p.push('...');
		const s = Math.max(2, cur - 1), e = Math.min(total - 1, cur + 1);
		for (let j = s; j <= e; j++) p.push(j);
		if (cur < total - 2) p.push('...');
		p.push(total);
		return p;
	}

	pagination && pagination.addEventListener('click', e => {
		const btn = e.target.closest('[data-page]');
		if (!btn || btn.disabled) return;
		const p = parseInt(btn.dataset.page, 10);
		if (isNaN(p) || p < 1 || p > state.totalPages || p === state.page) return;
		state.page = p;
		loadList();
	});

	/* ── Filtreler ── */
	filterStatus && filterStatus.addEventListener('change', () => { state.status = filterStatus.value; state.page = 1; loadList(); });
	filterSort   && filterSort.addEventListener('change',   () => { state.sort = filterSort.value;     state.page = 1; loadList(); });

	let searchTimer;
	searchEl && searchEl.addEventListener('input', () => {
		clearTimeout(searchTimer);
		searchTimer = setTimeout(() => { state.search = searchEl.value.trim(); state.page = 1; loadList(); }, 300);
	});

	/* ── Toplu seçim ── */
	checkAll && checkAll.addEventListener('change', () => {
		document.querySelectorAll('.mrrs-cb').forEach(cb => { cb.checked = checkAll.checked; });
	});

	function getCheckedIds() {
		return Array.from(document.querySelectorAll('.mrrs-cb:checked')).map(cb => parseInt(cb.value, 10));
	}

	/* ── Toplu işlem ── */
	bulkApply && bulkApply.addEventListener('click', () => {
		const action = bulkAction?.value;
		const ids    = getCheckedIds();
		if (!ids.length || !action) return;

		if (action === 'delete') {
			if (!confirm(`${ids.length} öneri silinecek. Emin misiniz?`)) return;
			ajax('mrrs_admin_delete', { ids }).then(res => {
				showNotice(res.data?.message || 'Silindi.', res.success ? 'success' : 'error');
				loadList();
			});
		} else {
			ajax('mrrs_admin_bulk_status', { ids, status: action }).then(res => {
				showNotice(res.data?.message || 'Güncellendi.', res.success ? 'success' : 'error');
				loadList();
			});
		}
	});

	/* ── Tablo eylemleri (onayla/reddet/düzenle/sil) ── */
	tbody && tbody.addEventListener('click', e => {
		const target = e.target;

		if (target.classList.contains('mrrs-quick-approve')) {
			ajax('mrrs_admin_bulk_status', { ids: [target.dataset.id], status: 'approved' }).then(res => {
				showNotice(res.data?.message || 'Onaylandı.', res.success ? 'success' : 'error');
				loadList();
			});
		}

		if (target.classList.contains('mrrs-quick-reject')) {
			// Red isleminde modal ac - admin notu girilebilsin
			openModal(parseInt(target.dataset.id, 10));
			const statusEl = document.getElementById('mrrs-edit-status');
			if (statusEl) { statusEl.value = 'rejected'; toggleAdminNoteRow('rejected'); }
		}

		if (target.classList.contains('mrrs-delete-btn')) {
			if (!confirm('Bu öneriyi silmek istiyor musunuz?')) return;
			ajax('mrrs_admin_delete', { ids: [target.dataset.id] }).then(res => {
				showNotice(res.data?.message || 'Silindi.', res.success ? 'success' : 'error');
				loadList();
			});
		}

		if (target.classList.contains('mrrs-edit-btn')) {
			openModal(parseInt(target.dataset.id, 10));
		}
	});

	/* ── Modal ── */
	function openModal(id) {
		ajax('mrrs_admin_get_request', { id }).then(res => {
			if (!res.success) { showNotice(res.data?.message || 'Hata.', 'error'); return; }
			const item = res.data.item;
			document.getElementById('mrrs-edit-id').value     = item.id;
			document.getElementById('mrrs-edit-title').value  = item.title;
			document.getElementById('mrrs-edit-source').value = item.source_link || '';
			document.getElementById('mrrs-edit-desc').value   = item.description || '';
			document.getElementById('mrrs-edit-status').value = item.status || 'pending';
			const noteEl = document.getElementById('mrrs-edit-admin-note');
			if (noteEl) noteEl.value = item.admin_note || '';
			toggleAdminNoteRow(item.status);
			modal.style.display = 'flex';
		});
	}

	/* ── Admin notu satırını duruma göre göster/gizle ── */
	function toggleAdminNoteRow(status) {
		const row = document.getElementById('mrrs-admin-note-row');
		if (row) row.style.display = status === 'rejected' ? '' : 'none';
	}
	const editStatus = document.getElementById('mrrs-edit-status');
	editStatus && editStatus.addEventListener('change', () => toggleAdminNoteRow(editStatus.value));

	modalClose && modalClose.addEventListener('click', () => { modal.style.display = 'none'; });
	modal && modal.addEventListener('click', e => { if (e.target === modal) modal.style.display = 'none'; });

	modalSave && modalSave.addEventListener('click', () => {
		const id = document.getElementById('mrrs-edit-id').value;
		ajax('mrrs_admin_save_request', {
			id:          id,
			title:       document.getElementById('mrrs-edit-title').value,
			source_link: document.getElementById('mrrs-edit-source').value,
			description: document.getElementById('mrrs-edit-desc').value,
			status:      document.getElementById('mrrs-edit-status').value,
			admin_note:  (document.getElementById('mrrs-edit-admin-note') || {}).value || '',
		}).then(res => {
			modal.style.display = 'none';
			showNotice(res.data?.message || (res.success ? 'Kaydedildi.' : 'Hata.'), res.success ? 'success' : 'error');
			loadList();
		});
	});

	/* ── Yardımcılar ── */
	function escHtml(str) {
		return String(str)
			.replace(/&/g, '&')
			.replace(/</g, '<')
			.replace(/>/g, '>')
			.replace(/"/g, '"');
	}

	function formatDate(dateStr) {
		if (!dateStr) return '';
		const d = new Date(dateStr);
		if (isNaN(d)) return '';
		return d.toLocaleDateString('tr-TR', { day: '2-digit', month: 'short', year: 'numeric' });
	}

	// URL'deki status parametresini uygula
	const urlParams = new URLSearchParams(window.location.search);
	if (urlParams.get('status') && filterStatus) {
		filterStatus.value = urlParams.get('status');
		state.status = urlParams.get('status');
	}

	loadList();
})();