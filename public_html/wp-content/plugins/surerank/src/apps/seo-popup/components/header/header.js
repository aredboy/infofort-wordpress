import { SureRankFullLogo } from '@/global/components/icons';
import { Button } from '@bsf/force-ui';
import { X } from 'lucide-react';
import { ENABLE_PAGE_LEVEL_SEO } from '@Global/constants';
import PageSeoCheckStatusButton from '@SeoPopup/components/header/page-seo-check-status-button';
import { isBricksBuilder } from '@SeoPopup/components/page-seo-checks/analyzer/utils/page-builder';
import { applyFilters } from '@wordpress/hooks';

const ConditionalLogo = () => {
	const renderLogo = applyFilters( 'surerank-pro.seo-popup-logo' );

	if ( renderLogo ) {
		return renderLogo;
	}

	return <SureRankFullLogo className="w-32 h-5" />;
};

const Header = ( { onClose } ) => {
	const SeoTabsComponent = applyFilters( 'surerank-pro.seo-popup' );

	return (
		<div className="flex items-center justify-between gap-10 border-0 border-b-0.5 border-solid border-border-subtle">
			<div className="flex items-center py-5 px-4">
				<ConditionalLogo />
			</div>
			<div className="flex items-center gap-2">{ SeoTabsComponent }</div>
			<div className="flex items-center py-5 px-4 gap-2">
				{ ENABLE_PAGE_LEVEL_SEO &&
					! isBricksBuilder() &&
					! SeoTabsComponent && (
						<PageSeoCheckStatusButton showAsBadge />
					) }
				<Button
					variant="ghost"
					size="sm"
					onClick={ onClose }
					className="p-1 text-icon-secondary hover:text-icon-primary hover:bg-transparent bg-transparent focus:outline-none"
					icon={ <X /> }
				/>
			</div>
		</div>
	);
};

export default Header;
