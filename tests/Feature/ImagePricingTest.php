<?php
namespace Tests\Feature;
use App\Services\ImagePricing;
use Tests\TestCase;
class ImagePricingTest extends TestCase {
 public function test_image_reservation_uses_2048_tokens_per_reference_and_output(): void {
  $pricing=new ImagePricing;
  $rates=[['billable'=>'input_text','unit'=>'token','cost_usd'=>0.000005],['billable'=>'input_image','unit'=>'token','cost_usd'=>0.000008],['billable'=>'output_image','unit'=>'token','cost_usd'=>0.00003]];
  $expected=(int)ceil(((strlen('Envelope')+1024)*5+2048*8+2048*30)*1.05);
  $this->assertSame($expected,$pricing->quote($rates,[],'Envelope',[[]]));
  $this->assertSame($expected,$pricing->quote($rates,['resolution'=>'4K'],'Envelope',[[]]));
 }
 public function test_zero_cost_byok_uses_upstream_total_or_complete_breakdown_once(): void {
  $p=new ImagePricing;
  $usage=['cost'=>0,'cost_details'=>['upstream_inference_cost'=>0.054928,'upstream_inference_prompt_cost'=>0.013768,'upstream_inference_completions_cost'=>0.04116]];
  $this->assertSame(54928,$p->cost([],[],$usage,[],[]));
  unset($usage['cost_details']['upstream_inference_cost']);$this->assertSame(54928,$p->upstreamCost($usage));
  unset($usage['cost_details']['upstream_inference_prompt_cost']);$this->assertNull($p->upstreamCost($usage));
  $this->assertNull($p->upstreamCost(['cost'=>0,'cost_details'=>['upstream_inference_cost'=>-1]]));
  $this->assertNull($p->upstreamCost(['cost'=>0,'cost_details'=>['upstream_inference_cost'=>'NaN']]));
 }
 public function test_automatic_routing_reserves_highest_quote_without_guessing_the_chosen_provider(): void {
  $p=new ImagePricing;$snapshot=['endpoints'=>[
   ['provider_name'=>'First','pricing'=>[['billable'=>'output_image','unit'=>'image','cost_usd'=>0.05]]],
   ['provider_name'=>'Second','pricing'=>[['billable'=>'output_image','unit'=>'image','cost_usd'=>0.1]]]
  ]];
  $this->assertSame(105000,$p->quote($snapshot,[],'Porch',[]));
  $this->assertSame(50000,$p->cost($snapshot,[],[],[],[['width'=>1024,'height'=>1024]],'First'));
  $this->assertNull($p->cost($snapshot,[],[],[],[['width'=>1024,'height'=>1024]]));
 }
 public function test_token_images_ignore_provider_cost_and_require_usage(): void {
  $p=new ImagePricing;$rates=[['billable'=>'input_text','unit'=>'token','cost_usd'=>0.000005],['billable'=>'output_image','unit'=>'token','cost_usd'=>0.00003]];
  $this->assertSame(125750,$p->cost($rates,[],['prompt_tokens'=>100,'completion_tokens'=>4175,'cost'=>0],[],[['width'=>1024,'height'=>1024]]));
  $this->assertNull($p->cost($rates,[],['cost'=>0],[],[['width'=>1024,'height'=>1024]]));
  $this->assertLessThan(1000000,$p->quote($rates,[],'A porch',[]));
 }
 public function test_mixed_input_tokens_are_not_double_counted(): void {
  $p=new ImagePricing;$rates=[['billable'=>'input_text','unit'=>'token','cost_usd'=>0.000005],['billable'=>'input_image','unit'=>'token','cost_usd'=>0.000008],['billable'=>'output_image','unit'=>'token','cost_usd'=>0.00003]];
  $refs=[['width'=>1024,'height'=>1024]];$outputs=[['width'=>1024,'height'=>1024]];
  $this->assertSame(37700,$p->cost($rates,[],['prompt_tokens'=>1000,'prompt_tokens_details'=>['image_tokens'=>900],'completion_tokens'=>1000],$refs,$outputs));
  $this->assertNull($p->cost($rates,[],['prompt_tokens'=>1000,'completion_tokens'=>1000],$refs,$outputs));
  $this->assertGreaterThan(0,$p->quote($rates,[],'Porch',$refs));
 }
 public function test_tiered_flat_and_megapixel_prices(): void {
  $p=new ImagePricing;$rates=[['billable'=>'output_image','unit'=>'image','cost_usd'=>0.05,'variant'=>'2k'],['billable'=>'output_image','unit'=>'image','cost_usd'=>0.1,'variant'=>'4k'],['billable'=>'input_reference','unit'=>'image','cost_usd'=>0.01]];
  $this->assertSame(60000,$p->cost($rates,['resolution'=>'2K'],[],[[]],[['width'=>2048,'height'=>2048]]));
  $this->assertNull($p->cost($rates,[],[],[[]],[['width'=>2048,'height'=>2048]]));
  $this->assertSame(20000,$p->cost([['billable'=>'output_image','unit'=>'megapixel','cost_usd'=>0.01]],[],[],[],[['width'=>2000,'height'=>1000]]));
 }
}
