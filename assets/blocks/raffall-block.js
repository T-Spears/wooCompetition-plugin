( function ( wp ) {
	if ( ! wp || ! wp.blocks || ! wp.element || ! wp.components ) {
		return;
	}

	const blockEditor = wp.blockEditor || wp.editor;
	if ( ! blockEditor || ! blockEditor.InspectorControls ) {
		return;
	}

	const { registerBlockType } = wp.blocks;
	const { createElement: el, useState, useEffect } = wp.element;
	const { PanelBody, SelectControl, ToggleControl, TextControl, Button } = wp.components;
	const { InspectorControls } = blockEditor;
	const apiFetch = wp.apiFetch;

	if ( typeof registerBlockType !== 'function' || typeof apiFetch !== 'function' ) {
		return;
	}

	registerBlockType('woo-competitions/card', {
		title: 'Competition Card',
		icon: 'awards',
		category: 'widgets',
		attributes: {
			productId: { type: 'number' },
			showCountdown: { type: 'boolean', default: true },
			showProgress: { type: 'boolean', default: true },
		},

		edit: function (props) {
			const { attributes, setAttributes } = props;
			const [search, setSearch] = useState('');
			const [options, setOptions] = useState([]);
			const [loading, setLoading] = useState(false);
			const [lastQuery, setLastQuery] = useState('');

			useEffect(function () {
				// initial fetch
				fetchProducts('');
			}, []);

			function fetchProducts(q) {
				setLoading(true);
				apiFetch({ path: '/raffall/v1/competitions?search=' + encodeURIComponent(q) + '&per_page=30' })
					.then(function (res) {
						const opts = [{ label: '-- Choose competition --', value: 0 }].concat(res.map(function (c) {
							return { label: c.title + (c.price ? (' — ' + c.price) : ''), value: c.id };
						}));
						setOptions(opts);
					})
					.catch(function () { setOptions([{ label: '-- No results --', value: 0 }]); })
					.finally(function () { setLoading(false); setLastQuery(q); });
			}

			return el('div', { className: 'raffall-block-editor' },
				el(InspectorControls, null,
					el(PanelBody, { title: 'Settings', initialOpen: true },
						el(TextControl, {
							label: 'Search competitions',
							value: search,
							onChange: function (v) { setSearch(v); },
							placeholder: 'Search by title...',
						}),
						el(Button, {
							isPrimary: true,
							isBusy: loading,
							onClick: function () { fetchProducts(search); }
						}, 'Search'),
						el(SelectControl, {
							label: 'Competition product',
							value: attributes.productId || 0,
							options: options,
							onChange: function (val) { setAttributes({ productId: parseInt(val, 10) || 0 }); }
						}),
						el(ToggleControl, {
							label: 'Show countdown',
							checked: attributes.showCountdown,
							onChange: function (v) { setAttributes({ showCountdown: v }); }
						}),
						el(ToggleControl, {
							label: 'Show progress',
							checked: attributes.showProgress,
							onChange: function (v) { setAttributes({ showProgress: v }); }
						})
					)
				),
				el('div', { className: 'raffall-block-preview', style: { border: '1px solid #eee', padding: '12px', borderRadius: '8px' } },
					(function () {
						const selectedId = attributes.productId || 0;
						const found = options.find(function (o) { return o.value === selectedId; });
						if (selectedId && found) {
							return el('div', { style: { display: 'flex', gap: '12px', alignItems: 'center' } },
								el('div', { style: { width: 96, height: 96, background:'#f6f6f6', borderRadius:8 } }),
								el('div', {},
									el('h4', { style: { margin: '0 0 6px' } }, found.label),
									el('div', { style: { color: '#666', marginBottom: 8 } }, lastQuery ? 'Search: ' + lastQuery : ''),
									el('div', { style: { fontWeight: 700 } }, '')
								)
							);
						}
						return el('div', { style: { color: '#666' } }, 'Search and select a competition to preview.');
					})()
				)
			);
		},

		save: function () {
			return null;
		}
	});
} )( window.wp );
