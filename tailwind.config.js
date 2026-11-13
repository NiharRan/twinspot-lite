/** Scoped to #twinspot-root and no preflight, so wp-admin styles are never touched. */
module.exports = {
	important: '#twinspot-root',
	corePlugins: { preflight: false },
	content: [ './src/**/*.{js,jsx,ts,tsx}' ],
	theme: {
		extend: {
			colors: {
				primary: { DEFAULT: '#0F6E5E', hover: '#0A5A4D', press: '#084A40', soft: '#F1F6F4', tint: '#DCEBE6' },
				accent: { DEFAULT: '#B7791F', soft: '#FBF4E4', line: '#E6D2AE', ink: '#7A4E0A' },
				danger: { DEFAULT: '#9B2C2C', soft: '#FAEEEE', line: '#E4BDBD' },
				success: { DEFAULT: '#1F8A4C', soft: '#EDF6F0', line: '#BFDDCB' },
				ink: '#132120',
				muted: '#55605E',
				line: '#DCE2DF',
				canvas: '#F2F4F3',
				info: '#EAEFED',
			},
			boxShadow: {
				card: '0 1px 3px 0 rgba(0,0,0,0.04), 0 1px 2px -1px rgba(0,0,0,0.02)',
				pop: '0 4px 6px -1px rgba(0,0,0,0.06), 0 2px 4px -2px rgba(0,0,0,0.03)',
			},
		},
	},
};
