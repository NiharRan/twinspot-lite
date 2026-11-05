/** Scoped to #twinspot-root and no preflight, so wp-admin styles are never touched. */
module.exports = {
	important: '#twinspot-root',
	corePlugins: { preflight: false },
	content: [ './src/**/*.{js,jsx,ts,tsx}' ],
	theme: {
		extend: {
			colors: {
				primary: { DEFAULT: '#0F766E', hover: '#115E59', press: '#134E4A', soft: '#F0FDFA', tint: '#CCFBF1' },
				accent: { DEFAULT: '#F59E0B', soft: '#FFFBEB', line: '#FDE68A' },
				danger: { DEFAULT: '#DC2626', soft: '#FEF2F2', line: '#FECACA' },
				success: { DEFAULT: '#16A34A', soft: '#F0FDF4', line: '#BBF7D0' },
				ink: '#1E293B',
				muted: '#64748B',
				line: '#E2E8F0',
				canvas: '#F6F7F7',
				info: '#EEF2FF',
			},
			boxShadow: {
				card: '0 1px 3px 0 rgba(0,0,0,0.04), 0 1px 2px -1px rgba(0,0,0,0.02)',
				pop: '0 4px 6px -1px rgba(0,0,0,0.06), 0 2px 4px -2px rgba(0,0,0,0.03)',
			},
		},
	},
};
