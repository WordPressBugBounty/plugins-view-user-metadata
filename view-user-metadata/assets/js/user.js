let SS88_VUM = {

    init: ()=>{

        SS88_VUM.initToggle();
		SS88_VUM.deleteBtns();
		SS88_VUM.lockBtns();
		SS88_VUM.initExport();
        SS88_VUM.initFocus();
        
    },
    initToggle: ()=>{

        let OnorOff = localStorage.getItem('SS88-VUM');

        if(OnorOff==null) {
            
            OnorOff = true;
            localStorage.setItem('SS88-VUM', OnorOff);

        }

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
        
        if(checked===true || checked===false) localStorage.setItem('SS88-VUM', checked);

    },
	deleteBtns: () => {

		document.querySelectorAll('button.btn-delete[data-key]').forEach((button)=>{

			button.addEventListener('click', (e) => {

				e.preventDefault();

				if( confirm(SS88_VUM_translations.confirm_delete) ) {

					fetch(ajaxurl, {

						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: new URLSearchParams({ action: 'SS88_VUM_delete', key: button.dataset.key, uid: button.dataset.uid, nonce: SS88_VUM_translations.nonce }).toString(),
					
					}).then(function(response) {
	
						return response.json();
	
					}).then(function(response) {
	
						if(response.success) {
	
							alert(SS88_VUM_translations.success + ' ' + response.data.body);
							button.parentElement.parentElement.parentElement.remove();
							document.querySelector('#SS88-VUM-table').classList.remove('ss88-focus');
	
						}
						else {

							const HttpCode = (response && response.data && typeof response.data.httpcode !== 'undefined') ? response.data.httpcode : 'unknown';
							const Message = (response && response.data && response.data.body) ? response.data.body : 'The server returned an unexpected response.';
	
							alert(SS88_VUM_translations.error + ' ' + HttpCode + ': ' + Message);
	
						}
	
					}).catch( err => { console.log(err); alert(SS88_VUM_translations.error + ' ' + err.message); } );

				}
	
			});

		});

	},
	lockBtns: () => {

		document.querySelectorAll('button.btn-lock[data-lock]').forEach((button)=>{

			button.addEventListener('click', (e) => {

				e.preventDefault();

				const deleteBtn = button.parentElement.querySelector('button.btn-delete[data-key]');

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
			menu.classList.toggle('is-open');

		});

		menu.querySelectorAll('button[data-format]').forEach((button)=>{

			button.addEventListener('click', (e) => {

				e.preventDefault();
				menu.classList.remove('is-open');
				SS88_VUM.exportMeta(button.dataset.format, wrapper.dataset.uid);

			});

		});

		document.addEventListener('click', (e) => {

			if(!e.target.closest('#SS88VUM-export-wrap')) menu.classList.remove('is-open');

		});

	},
	exportMeta: (format, uid) => {

		if(!format || !uid) {

			alert(SS88_VUM_translations.error + ' Missing export parameters.');
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
				const Message = (response && response.data && response.data.body) ? response.data.body : 'The export response was invalid.';
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
		URL.revokeObjectURL(link.href);

	},
    initFocus: () => {

        document.querySelectorAll('#SS88-VUM-table-wrapper tr').forEach(tr => {

            tr.addEventListener('click', (e)=>{

                if(e.target.closest('button.btn-delete, button.btn-lock')) return;

                document.querySelectorAll('#SS88-VUM-table-wrapper tr').forEach(tr => { tr.classList.remove('ss88-focus') });
                document.querySelector('#SS88-VUM-table').classList.toggle('ss88-focus');
                tr.classList.add('ss88-focus');

            })

        })

    }

}

window.addEventListener('DOMContentLoaded', (event) => {

	if(document.querySelector('#SS88-VUM-table-wrapper')) SS88_VUM.init();

});
