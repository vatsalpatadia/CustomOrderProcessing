# Networld_CustomOrderProcessing module

Custom Module for changing order status via Magento REST API and track change history in custom database table.

## Module Overview
    The module introduces the following features:

    => Custom REST API Endpoint: Allows external systems to update the status of an existing magento order using a POST request.
    => Order Status change history tracking: Captures and logs order status changes (including order ID, old status, new status, and timestamp) into a custom database table.

## Module Details
    Namespace: Networld/CustomOrderProcessing
    Module Name: CustomOrderProcessing
    Database Table: networld_order_processing_status

## Installation
    Place the Module Files:
    Copy the module folder (Networld/CustomOrderProcessing) into the app/code/ directory of your Magento 2 installation.
    Final path: app/code/Networld/CustomOrderProcessing
    Enable the Module: Run the following commands from the Magento root directory:

    php bin/magento setup:upgrade
    php bin/magento setup:di:compile
    php bin/magento setup:static-content:deploy
    php bin/magento cache:clean

## How to update order status using REST API
    => Custom REST API Endpoint
       Endpoint: POST /rest/V1/order-status-update
       Purpose: Updates the status of an order based on the provided order increment ID and new status.
       Sample Json Request parameters:
       {
        "data": {
            "order_increment_id": "000000002",
            "new_order_status": "processing"
        }
       }

    Response: Returns a success message or an error if the order is not valid or not fouind or the status update fails.

    => Use bearer token as Magento’s API authentication.
    To generate admin token via REST API, send a POST request to the following endpoint. It will return the token:

    Endpoint: V1/integration/admin/token
    Post Data: {
                "username": "<admin_username>",
                "password": "<admin_password>"
            }
    

## Order Status Change History
    Event: Triggered sales_order_save_after event whenever an order status is updated (via API or otherwise).
    Observer: Listens to the sales_order_save_after event.
    Action: Upton event dispatch, it will save following details into the networld_order_processing_status table:

    order_id: The ID of the order.
    old_status: The previous status of the order.
    current_status: The updated status of the order.
    created_at & update_at: The date and time of the status change.


## Checking the history from database
    Query the networld_order_processing_status table in your database to view the logged status changes:

    SELECT * FROM networld_order_processing_status ORDER BY id DESC;

## Store Configuration

    Store configuration is created also to enable and disable the functionality.

    Path: Store-> Configuration-> Networld-> General Configuration -> Enable Order Status Update -> Yes/No 

## UNIT test coverage scenerios, please run below commands to execute this module specific  unit test cases
    Navigate to your project root directory from terminal and run below command for running unit test.

    vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Networld/CustomOrderProcessing/Test/Unit/Model/Api/OrderStatusUpdateSaveTest.php

    

