<div class="flex min-w-0 items-center gap-3 border-b border-stone-200 dark:border-stone-700"><nav aria-label="Story editor tabs" class="flex min-w-0 flex-1 gap-5 overflow-x-auto text-sm">
@foreach(['page'=>'Page plan','visuals'=>'Scenes','images'=>'Image generation ↗','inventory'=>'Inventory','story'=>'Story bible','audio'=>'Audio'] as $key=>$label)
@if($generation ?? false)
<a href="{{ $key==='images'?route('images.studio',[$story,$page,'visual'=>request('visual')]):route('stories.edit',[$story,'page'=>$page->id,'tab'=>$key,'visual'=>request('visual')]) }}" @if($key==='images') aria-current="page" @else @click.prevent="if(await flush())window.location.assign($el.href)" @endif class="whitespace-nowrap border-b-2 pb-1 {{ $key==='images'?'border-stone-900 text-stone-900 dark:text-stone-100 dark:border-stone-100':'border-transparent text-stone-400' }}">{{ $label }}</a>
@elseif($key==='images')
<a class="whitespace-nowrap border-b-2 border-transparent pb-1 text-stone-400" href="{{ route('images.studio',[$story,$page]) }}" @click.prevent="if(tab==='page' || activePageId!=={{ $page->id }}){openImages();}else{window.vsgSceneEditor?.openGeneration('{{ route('images.studio',[$story,$page]) }}');}">{{ $label }}</a>
@else
<button type="button" @click="switchTab('{{ $key }}')" :aria-current="tab==='{{ $key }}'?'page':null" :class="tab==='{{ $key }}'?'border-stone-900 text-stone-900 dark:text-stone-100 dark:border-stone-100':'border-transparent text-stone-400'" class="whitespace-nowrap border-b-2 pb-1">{{ $label }}</button>
@endif
@endforeach
</nav><button type="button" class="btn-secondary shrink-0" aria-haspopup="dialog" @click="{{ ($generation ?? false)?'window.vsgSceneEditor?.preparePreview()':'testActivePage()' }}">Test scenes</button></div>
