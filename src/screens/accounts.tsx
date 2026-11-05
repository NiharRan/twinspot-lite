import { __ } from '@wordpress/i18n';
import { Card } from '../components/ui/card';
import { RingsTable, type TableProps } from './overview';

export function Accounts( props: Omit< TableProps, 'data' > & { data: TableProps[ 'data' ] | null } ) {
	if ( ! props.data ) {
		return <Card className="p-10 text-center text-muted">{ __( 'Loading…', 'twinspot-lite' ) }</Card>;
	}
	return <RingsTable { ...props } data={ props.data } />;
}
