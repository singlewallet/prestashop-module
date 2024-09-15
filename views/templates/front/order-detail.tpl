<div class="box">
   <h3>{l s=' Payment Detail (SingleWallet) ' d='Shop.Theme.Customeraccount'}</h3>
   <div style="overflow-y:auto">
      <table class="table table-bordered">
         <thead class="thead-default">
            <tr>
               <th>{l s='Txid' d='Shop.Theme.Global'}</th>
               <th>{l s='Status' d='Shop.Theme.Checkout'}</th>
               <th>{l s='Invoice Amount' d='Shop.Theme.Global'}</th>
               <th>{l s='Fiat Invoice Amount' d='Shop.Theme.Checkout'}</th>
               <th>{l s='Paid Amount' d='Shop.Theme.Checkout'}</th>
               <th>{l s='Fiat Paid Amount' d='Shop.Theme.Checkout'}</th>
               <th>{l s='Exchange Rate' d='Shop.Theme.Checkout'}</th>
               <th>{l s='Date ' d='Shop.Theme.Checkout'}</th>
            </tr>
         </thead>
         <tbody>
         {if isset($payments) && count($payments)}
            {foreach from=$payments item=payment}
               <tr>
                  <td><a href="{$payment['txid_url']}" target="_blank">{substr($payment['txid'],0,10)}...</a></td>
                  <td>{$payment['status']}{if ($payment['exception'] != 'none')} ({$payment['exception']}){/if}</td>
                  <td>{$payment['invoice_amount']} USDT</td>
                  <td>{$payment['fiat_invoice_amount']} {$payment['currency_code']}</td>
                  <td>{$payment['paid_amount']} USDT</td>
                  <td>{$payment['fiat_paid_amount']} {$payment['currency_code']}</td>
                  <td>{$payment['exchange_rate']}</td>
                  <td>{$payment['created_at']}</td>
               </tr>
            {/foreach}
         {else}
            <tr><td colspan="8" style="text-align: center;">No Payments yet.</td></tr>
         {/if}

         </tbody>
      </table>
   </div>
   <div>
      {if count($payments) == 0 || $is_underpaid}
         <a class="btn btn-primary form-control-submit" href="{$pay_url}">Pay Now</a>
      {/if}
   </div>
</div>