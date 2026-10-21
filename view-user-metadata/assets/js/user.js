let SS88_VUM = {

    init: ()=>{

        SS88_VUM.initToggle();
		SS88_VUM.deleteBtns();
		SS88_VUM.lockBtns();
		SS88_VUM.initExport();
        SS88_VUM.initFocus();
        SS88_VUM.initCopy();
        SS88_VUM.initRaw();
        
    },
    initToggle: ()=>{

        let OnorOff = null;

        try { OnorOff = localStorage.getItem('SS88-VUM'); } catch(err) {}

        OnorOff = (OnorOff==='false') ? false : true;

        SS88_VUM.toggleView(OnorOff);
        SS88_VUM.toggleToggle(OnorOff);

        document.querySelector('#SS88VUM-toggle').addEventListener('change', (e) => {

            SS88_VUM.toggleView(e.target.checked);
            SS88_VUM.toggleToggle(e.target.checked);
            SS88_VUM.store(e.target.checked);

            if(e.target.checked) {

                setTimeout(()=>{
                
                    const element = document.getElementById('SS88-VUM-table-wrapper');
                    const y = element.getBoundingClientRect().top + window.pageYOffset + -100;
                    window.scrollTo({top: y, behavior: 'smooth'});
                
                }, 200);

            }

        });

        if(document.querySelector('#acf-extended-admin-css')) document.querySelector('#SS88-VUM-table-wrapper').classList.add('has-acfe');

    },
    toggleView: (checked) =>{

        document.querySelector('#SS88-VUM-table-wrapper').style.display = (checked) ? 'block' : 'none';

    },
    toggleToggle: (checked) =>{

        document.querySelector('#SS88VUM-toggle').checked = checked;

    },
    store: (checked) => {
        
        if(checked===true || checked===false) {

            try { localStorage.setItem('SS88-VUM', checked); } catch(err) {}

        }

    },
	deleteBtns: () => {

		document.querySelectorAll('button.ss88-vum-delete[data-key]').forEach((button)=>{

			button.addEventListener('click', (e) => {

				e.preventDefault();

				if( confirm(button.dataset.confirm) ) {

					fetch(ajaxurl, {

						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: new URLSearchParams({ action: 'SS88_VUM_delete', key: button.dataset.key, uid: button.dataset.uid, nonce: SS88_VUM_translations.nonce }).toString(),
					
					}).then(function(response) {
	
						return response.json();
	
					}).then(function(response) {
	
						if(response.success) {
	
							alert(SS88_VUM_translations.success + ' ' + response.data.body);
							button.closest('tr').remove();
							document.querySelector('#SS88-VUM-table').classList.remove('ss88-focus');
	
						}
						else {

							const HttpCode = (response && response.data && typeof response.data.httpcode !== 'undefined') ? response.data.httpcode : 'unknown';
							const Message = (response && response.data && response.data.body) ? response.data.body : SS88_VUM_translations.unexpected_response;
	
							alert(SS88_VUM_translations.error + ' ' + HttpCode + ': ' + Message);
	
						}
	
					}).catch( err => { console.log(err); alert(SS88_VUM_translations.error + ' ' + err.message); } );

				}
	
			});

		});

	},
	lockBtns: () => {

		document.querySelectorAll('button.ss88-vum-lock[data-lock]').forEach((button)=>{

			button.addEventListener('click', (e) => {

				e.preventDefault();

				const deleteBtn = button.parentElement.querySelector('button.ss88-vum-delete[data-key]');

				if(!deleteBtn) return;

				if(button.classList.contains('is-locked')) {

					button.classList.remove('is-locked');
					button.classList.add('is-unlocked');
					button.title = SS88_VUM_translations.unlocked_title;
					button.setAttribute('aria-label', SS88_VUM_translations.unlocked_title);
					button.querySelector('.dashicons').classList.remove('dashicons-lock');
					button.querySelector('.dashicons').classList.add('dashicons-unlock');
					deleteBtn.classList.remove('is-hidden');

				}
				else {

					button.classList.remove('is-unlocked');
					button.classList.add('is-locked');
					button.title = SS88_VUM_translations.locked_title;
					button.setAttribute('aria-label', SS88_VUM_translations.locked_title);
					button.querySelector('.dashicons').classList.remove('dashicons-unlock');
					button.querySelector('.dashicons').classList.add('dashicons-lock');
					deleteBtn.classList.add('is-hidden');

				}

			});

		});

	},
	initExport: () => {

		const trigger = document.querySelector('#SS88VUM-export-trigger');
		const menu = document.querySelector('#SS88VUM-export-menu');
		const wrapper = document.querySelector('#SS88-VUM-table-wrapper');

		if(!trigger || !menu || !wrapper) return;

		trigger.addEventListener('click', (e) => {

			e.preventDefault();
			SS88_VUM.setExportMenu(!menu.classList.contains('is-open'));

		});

		menu.querySelectorAll('button[data-format]').forEach((button)=>{

			button.addEventListener('click', (e) => {

				e.preventDefault();
				SS88_VUM.setExportMenu(false);
				SS88_VUM.exportMeta(button.dataset.format, wrapper.dataset.uid);

			});

		});

		document.addEventListener('click', (e) => {

			if(!e.target.closest('#SS88VUM-export-wrap')) SS88_VUM.setExportMenu(false);

		});

		document.addEventListener('keydown', (e) => {

			if(e.key === 'Escape' && menu.classList.contains('is-open')) {

				SS88_VUM.setExportMenu(false);
				trigger.focus();

			}

		});

	},
	setExportMenu: (open) => {

		document.querySelector('#SS88VUM-export-menu').classList.toggle('is-open', open);
		document.querySelector('#SS88VUM-export-trigger').setAttribute('aria-expanded', open ? 'true' : 'false');

	},
	exportMeta: (format, uid) => {

		if(!format || !uid) {

			alert(SS88_VUM_translations.error + ' ' + SS88_VUM_translations.missing_export);
			return;

		}

		fetch(ajaxurl, {

			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: new URLSearchParams({ action: 'SS88_VUM_export', uid: uid, format: format, nonce: SS88_VUM_translations.export_nonce }).toString(),

		}).then(function(response) {

			return response.json();

		}).then(function(response) {

			if(response && response.success && response.data && response.data.content) {

				SS88_VUM.downloadFile(response.data.filename, response.data.mime, response.data.content);

			}
			else {

				const HttpCode = (response && response.data && typeof response.data.httpcode !== 'undefined') ? response.data.httpcode : 'unknown';
				const Message = (response && response.data && response.data.body) ? response.data.body : SS88_VUM_translations.invalid_export;
				alert(SS88_VUM_translations.error + ' ' + HttpCode + ': ' + Message);

			}

		}).catch( err => { console.log(err); alert(SS88_VUM_translations.error + ' ' + err.message); } );

	},
	downloadFile: (filename, mime, content) => {

		const file = new Blob([content], {type: mime || 'text/plain;charset=utf-8'});
		const link = document.createElement('a');

		link.href = URL.createObjectURL(file);
		link.download = filename || 'user-meta-export.txt';
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
		setTimeout(() => URL.revokeObjectURL(link.href), 1000);

	},
    initFocus: () => {

        document.querySelectorAll('#SS88-VUM-table-wrapper tr').forEach(tr => {

            tr.addEventListener('click', (e)=>{

                if(e.target.closest('button.ss88-vum-delete, button.ss88-vum-lock, button.ss88-vum-copy')) return;

                const table = document.querySelector('#SS88-VUM-table');
                const wasFocused = table.classList.contains('ss88-focus') && tr.classList.contains('ss88-focus');

                document.querySelectorAll('#SS88-VUM-table-wrapper tr').forEach(row => { row.classList.remove('ss88-focus') });

                if(wasFocused) table.classList.remove('ss88-focus');
                else {

                    table.classList.add('ss88-focus');
                    tr.classList.add('ss88-focus');

                }

            })

        })

    },
	initCopy: () => {

		document.querySelectorAll('#SS88-VUM-table button.ss88-vum-copy').forEach((button)=>{

			button.addEventListener('click', (e) => {

				e.preventDefault();

				let Text = '';

				if(button.classList.contains('ss88-vum-copy-key')) Text = button.dataset.key;
				else {

					// Copy whichever view is showing. Multiple values are joined with new lines.
					const Wrapper = document.querySelector('#SS88-VUM-table-wrapper');
					const Container = button.closest('td').querySelector(Wrapper.classList.contains('is-raw') ? '.ss88-vum-raw' : '.ss88-vum-formatted');

					Text = Array.from(Container.querySelectorAll('.ss88-vum-value')).map((el) => el.hasAttribute('data-empty') ? '' : el.textContent).join('\n');

				}

				SS88_VUM.copyText(Text).then(() => SS88_VUM.copyFeedback(button, true)).catch(() => SS88_VUM.copyFeedback(button, false));

			});

		});

	},
	copyText: (text) => {

		if(navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text);

		// The Clipboard API needs HTTPS, so fall back to a hidden textarea on plain HTTP admin screens.
		return new Promise((resolve, reject) => {

			const Area = document.createElement('textarea');
			const PreviousFocus = document.activeElement;
			let Copied = false;

			Area.value = text;
			Area.setAttribute('readonly', '');
			Area.style.position = 'fixed';
			Area.style.top = '0';
			Area.style.opacity = '0';
			document.body.appendChild(Area);
			Area.select();
			Area.setSelectionRange(0, text.length); // iOS Safari ignores select()

			try { Copied = document.execCommand('copy'); } catch(err) {}

			document.body.removeChild(Area);

			// Selecting the textarea moved focus, so return it to the button for keyboard users.
			if(PreviousFocus && PreviousFocus.focus) PreviousFocus.focus();

			if(Copied) resolve();
			else reject();

		});

	},
	copyFeedback: (button, ok) => {

		const Icon = button.querySelector('.dashicons');
		const Message = ok ? SS88_VUM_translations.copied : SS88_VUM_translations.copy_failed;

		if(!button.dataset.title) button.dataset.title = button.title;

		clearTimeout(button.SS88Timer);
		Icon.classList.remove('dashicons-admin-page', 'dashicons-yes', 'dashicons-no');
		Icon.classList.add(ok ? 'dashicons-yes' : 'dashicons-no');
		button.classList.add('is-copied');
		button.title = Message;

		if(window.wp && wp.a11y && wp.a11y.speak) wp.a11y.speak(Message);

		button.SS88Timer = setTimeout(() => {

			Icon.classList.remove('dashicons-yes', 'dashicons-no');
			Icon.classList.add('dashicons-admin-page');
			button.classList.remove('is-copied');
			button.title = button.dataset.title;

		}, 1500);

	},
	initRaw: () => {

		const Button = document.querySelector('#SS88VUM-raw-toggle');

		if(!Button) return;

		let Raw = false;

		try { Raw = (localStorage.getItem('SS88-VUM-raw') === 'true'); } catch(err) {}

		SS88_VUM.setRaw(Raw);

		Button.addEventListener('click', (e) => {

			e.preventDefault();

			const Next = Button.getAttribute('aria-pressed') !== 'true';

			SS88_VUM.setRaw(Next);

			try { localStorage.setItem('SS88-VUM-raw', Next); } catch(err) {}

		});

	},
	setRaw: (raw) => {

		document.querySelector('#SS88-VUM-table-wrapper').classList.toggle('is-raw', raw);
		document.querySelector('#SS88VUM-raw-toggle').setAttribute('aria-pressed', raw ? 'true' : 'false');

	}
}

window.addEventListener('DOMContentLoaded', (event) => {

	if(document.querySelector('#SS88-VUM-table-wrapper')) SS88_VUM.init();

});
