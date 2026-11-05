export type LinkType = 'email' | 'phone' | 'address';

export interface Ring {
	size: number;
	sample: boolean;
	links: LinkType[];
	orders: { id: number; url: string }[];
	total: string;
	last_order: string | null;
}

export interface Stats {
	indexed: number;
	rings: number;
	orders_in_rings: number;
	blocked_30d: number;
	customers: number;
	coupons: number;
	sample: boolean;
	version: string;
}

export interface Overview {
	stats: Stats;
	rings: Ring[];
	total: number;
	total_pages: number;
}

export interface Filters {
	search: string;
	min_size: number;
	link: '' | LinkType;
	page: number;
}
