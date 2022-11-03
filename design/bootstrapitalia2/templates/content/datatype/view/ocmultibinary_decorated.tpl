{if $attribute.has_content}

	{def $groups = ocmultibinary_available_groups($attribute)}
	{if count($groups)|eq(0)}{set $groups = $groups|append('')}{/if}
	{def $groups_count = count($groups)}
	{if $groups_count|eq(1)}
		<div class="row mx-lg-n3">
			{foreach $groups as $group}
				{if $group|ne('')}
				<div class="col-12 px-lg-3">
					<h6 class="no_toc">{$group|wash()}</h6>
				</div>
				{/if}
				{foreach ocmultibinary_list_by_group($attribute, $group) as $file}
				 <div class="col-md-6 px-lg-3 pb-lg-3">
					<div class="card card-teaser shadow p-4 mt-3 rounded border">
						{display_icon('it-clip', 'svg', 'icon')}
						<div class="card-body">
						  <h5 class="card-title">
							<a class="stretched-link" href={concat( 'ocmultibinary/download/', $attribute.contentobject_id, '/', $attribute.id,'/', $attribute.version , '/', $file.filename ,'/file/', $file.original_filename|urlencode )|ezurl}>
								{if $file.display_name|ne('')}{$file.display_name|wash( xhtml )}{else}{$file.original_filename|clean_filename()|wash( xhtml )}{/if}
							</a>
						  {if $file.display_text|ne('')}
							  <small class="d-block my-2">{$file.display_text|wash( xhtml )}</small>
						  {/if}
							<small class="d-block">(File {$file.mime_type|explode('application/')|implode('')} {$file.filesize|si( byte )})</small>
						  </h5>
						</div>
					</div>
				</div>
				{/foreach}
			{/foreach}
		</div>
	{else}
		{ezscript_require('jquery.quicksearch.min.js')}
		{run-once}
		<script>{literal}
			$(document).ready(function (){
				$('.multibinary-search').each(function (){
					var searchInput = $(this).find('input');
					var containerId = $(this).find('.link-list').attr('id');
					searchInput.quicksearch('#'+containerId+ ' li');
				});
			})
		{/literal}</script>
		{/run-once}
		<div class="accordion my-4 font-sans-serif" role="tablist">
			{foreach $groups as $index => $group}
			<div class="accordion-item">
				<h2 class="accordion-header" id="heading-{$attribute.id}-{$index}">
					<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{$attribute.id}-{$index}" aria-expanded="false" aria-controls="collapse-{$attribute.id}-{$index}">
						{$group|wash()}
					</button>
				</h2>
				<div id="collapse-{$attribute.id}-{$index}" class="accordion-collapse collapse" role="region" aria-labelledby="heading-{$attribute.id}-{$index}">
					{def $file_list = ocmultibinary_list_by_group($attribute, $group)}
					<div class="accordion-body pb-2{if count($file_list)|gt(3)} multibinary-search{/if}">
							{if count($file_list)|gt(3)}
							<div class="form-group mb-4">
								<input type="text"
									   class="form-control form-control-sm"
									   placeholder="{'Search in'|i18n('bootstrapitalia')} {$group|wash()}"
									   aria-invalid="false"/>
								<label class="d-none">
									{'Search in'|i18n('bootstrapitalia')} {$group|wash()}
								</label>
								<button class="autocomplete-icon btn btn-link" aria-label="{'Search'|i18n('openpa/search')}">
									{display_icon('it-search', 'svg', 'icon icon-sm')}
								</button>
							</div>
							{/if}
							<ul class="link-list mb-0" id="list-{$attribute.id}-{$index}">
								{foreach $file_list as $file}
									<li>
										<div class="cmp-icon-link mb-2 pb-2">
											<a class="list-item icon-left d-inline-block font-sans-serif" href={concat( 'ocmultibinary/download/', $attribute.contentobject_id, '/', $attribute.id,'/', $attribute.version , '/', $file.filename ,'/file/', $file.original_filename|urlencode )|ezurl}>
												<span class="list-item-title-icon-wrapper">
													{display_icon('it-clip', 'svg', 'icon')}
													<span class="list-item">
														{if $file.display_name|ne('')}{$file.display_name|wash( xhtml )}{else}{$file.original_filename|clean_filename()|wash( xhtml )}{/if}
														<em>
															File {$file.mime_type|explode('application/')|implode('')} {$file.filesize|si( byte )}
														{if $file.display_text|ne('')}
															<small class="d-block my-2">{$file.display_text|wash( xhtml )}</small>
														{/if}
														</em>
													</span>
												</span>
											</a>
										</div>
									</li>
								{/foreach}
							</ul>
{*						</div>*}
					</div>
					{undef $file_list}
				</div>
			</div>
			{/foreach}
		</div>
	{/if}

	{undef $groups $groups_count}
{/if}
