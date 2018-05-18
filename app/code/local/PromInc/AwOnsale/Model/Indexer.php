<?php
class PromInc_AwOnsale_Model_Indexer extends Mage_Core_Model_Abstract
{

	/**
	 * Reindex the AW OnSale index tables for products that have been recently purchased
	 *
	 * This replaces the inefficent method AW implemted of updating the index on order save
	 *   via the sales_order_save_after observer:
	 *   AW_Onsale_Model_Observer -> salesOrderSaveAfter
	 *
	 * This method is triggered by the Magento Cron and looks for when it was last to
	 *   determine what products to reindex.
	 *
	 * NOTE: According to Ahead Works, this re-index ONLY affects orders OnSale rules that
	 *         are based on quantity.  If you don't use qty based rules, it would be safe
	 *         to disable the cron task.
	 */
	public function refreshIndex() {
		// get time cron last run
		$tasks = Mage::getModel('cron/schedule')->getCollection()
			->addFieldToSelect('finished_at')
			->addFieldToFilter('job_code', 'prominc_awonsale_refreshindex')
			->addFieldToFilter('status', 'success');
		$tasks->getSelect()
			->limit(1)
			->order('finished_at DESC');

		$lastRun = $tasks->getFirstItem()->getFinishedAt();

		if( $lastRun ) {
			$resource = Mage::getSingleton('core/resource');
			$read = $resource->getConnection('core_read');
			if( Mage::getEdition() == 'Enterprise' ) {
				$tableOrderGrid = $resource->getTableName('sales/order_grid');
			} else {
				$tableOrderGrid = $resource->getTableName('sales/order');
			}
			$tableOrderItem = $resource->getTableName('sales/order_item');

			$orders = array();

			// get orders since last cron run
			$queryOrders = 'SELECT store_id, entity_id FROM ' . $tableOrderGrid . ' WHERE updated_at > "' . $lastRun . '"';
			$rowsOrders = $read->fetchAll( $queryOrders );
			foreach( $rowsOrders as $rowOrder ) {
				// define store
				if( !array_key_exists( $rowOrder['store_id'], $orders ) ) {
					$orders[ $rowOrder['store_id'] ] = array();
				}
				// add order from store
				if( !in_array( $rowOrder['entity_id'], $orders[ $rowOrder['store_id'] ] ) ) {
					$orders[ $rowOrder['store_id'] ][] = $rowOrder['entity_id'];
				}
			}

			if( count( $orders ) > 0 ) {
				foreach( $orders as $storeId => $orderIds ) {
					// get unique product ids
					$productIds = array();
					$queryProductIds = 'SELECT DISTINCT product_id FROM ' . $tableOrderItem . ' WHERE store_id = ' . $storeId . ' AND updated_at > "' . $lastRun . '" AND order_id IN (' . implode( ',', $orderIds ) . ')';

					$rowsProductIds = $read->fetchAll( $queryProductIds );
					foreach( $rowsProductIds as $rowsProductId ) {
						$productIds[] = $rowsProductId['product_id'];
					}

					// reindex product for store
					if (count($productIds) > 0) {
						$collection = Mage::getModel('onsale/rule')->getResourceCollection();
						$collection
							->addStoreFilter( $storeId )
							->addActiveFilter()
						;
						foreach ($collection as $rule) {
							$loadedRule = Mage::getModel('onsale/rule')->load( $rule->getId() );
							foreach( $productIds as $productId ) {
								$loadedRule->applyToProduct( $productId );
							}
						}
					}
				}
			}
		}
	}

}
