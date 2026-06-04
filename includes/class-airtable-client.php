<?php
// includes/class-airtable-client.php

class EDOA_Airtable_Client {

	private string $pat;
	private string $base;

	public function __construct() {
		$this->pat  = defined( 'EDOA_AIRTABLE_PAT' ) ? EDOA_AIRTABLE_PAT : '';
		$this->base = defined( 'EDOA_AIRTABLE_BASE_ID' ) ? EDOA_AIRTABLE_BASE_ID : '';
	}

	public function is_configured(): bool {
		return $this->pat !== '' && $this->base !== '';
	}

	/**
	 * Fetch all records from a table, following pagination.
	 * @param string $table   Table name e.g. "Reviews"
	 * @param array  $params  Extra query params (e.g. filterByFormula)
	 * @return array[] list of records: each ['id'=>recId, 'fields'=>[...]]
	 */
	public function fetch_all( string $table, array $params = array() ): array {
		$records = array();
		$offset  = null;
		do {
			$query = array_merge( $params, array( 'pageSize' => 100 ) );
			if ( $offset ) {
				$query['offset'] = $offset;
			}
			$url = sprintf(
				'https://api.airtable.com/v0/%s/%s?%s',
				rawurlencode( $this->base ),
				rawurlencode( $table ),
				http_build_query( $query )
			);
			$resp = wp_remote_get( $url, array(
				'headers' => array( 'Authorization' => 'Bearer ' . $this->pat ),
				'timeout' => 30,
			) );
			if ( is_wp_error( $resp ) ) {
				throw new RuntimeException( 'Airtable request failed: ' . $resp->get_error_message() );
			}
			$code = wp_remote_retrieve_response_code( $resp );
			if ( 200 !== (int) $code ) {
				throw new RuntimeException( 'Airtable HTTP ' . $code . ': ' . wp_remote_retrieve_body( $resp ) );
			}
			$body = json_decode( wp_remote_retrieve_body( $resp ), true );
			foreach ( ( $body['records'] ?? array() ) as $rec ) {
				$records[] = $rec;
			}
			$offset = $body['offset'] ?? null;
		} while ( $offset );

		return $records;
	}
}
